<?php

namespace App\Http\Controllers;

use function abort;
use function collect;
use function dump;
use function explode;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use function print_r;

class MarketController extends Controller
{

    private $collections;
    private $symbols;
    private $data;
    private $switchToYahoo = true;

    public function __construct()
    {
        $this->collections = collect([
            "dow",
            "nasdaq",
            "sp_500"
        ]);

        $this->symbols = collect([
            "DJIA",
            "IXIC",
            "INX"
        ]);

        $this->data = collect([
            ["symbol" => $this->symbols[0], "collection" => $this->collections[0]],
            ["symbol" => $this->symbols[1], "collection" => $this->collections[1]],
            ["symbol" => $this->symbols[2], "collection" => $this->collections[2]],
        ]);
    }

    /**
     *  Appends '_yahoo' to the collection names to switch to the yahoo dataset
     * @param $collection
     * @return string
     */
    private function sourceSwitch($collection)
    {
        if ($this->switchToYahoo)
            return $collection . "_yahoo";
        else
            return $collection;
    }

    /**
     * Takes the request and checks if the market names or symbol is correct. If correct, returns name, symbol and a boolean True; otherwise return False and null
     * @param $input (The request)
     * @return array
     */
    private function getInputInfo($input)
    {
//        if input is a collection name
        if ($this->collections->contains($input))
            return ["found" => true, "symbol" => $this->data->firstWhere("collection", $input)['symbol'], "collection" => $input];
//        if input is a symbol
        else if (($this->symbols->contains($input)))
            return ["found" => true, "symbol" => $input, "collection" => $this->data->firstWhere("symbol", $input)['collection']];
        else
            return ["found" => false, "symbol" => null, "collection" => null];
    }

    /**
     * Makes the data structure for the response object
     * @param $collectionData
     * @param $data
     * @return array
     */
    private function makeResponse($collectionData, $data)
    {
        return [
            "name" => $collectionData["collection"],
            "symbol" => $collectionData["symbol"],
            "data" => $data
        ];
    }

    /**
     * Fetch the data based on the given dates
     * @param Request $request
     * @param $collection - Name of the collection to save to
     * @return mixed
     * @throws ValidationException
     */
    public function getCollection(Request $request, $collection)
    {
        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);

        $this->validate($request, [
            "from" => "sometimes|date_format:Y-m-d",
            "to" => "required_with:from|date_format:Y-m-d|after_or_equal:from"
        ], [
            "date_format" => "date format invalid. Format: 1999-01-01",
            "required" => "This field is required.",
            "required_with" => " 'To' is required with 'From' ",
            "after_or_equal" => " 'To' field must be after or equal to 'From' "
        ]);


        $query = DB::collection($this->sourceSwitch($collection["collection"]));

        if ($request->has(["to", "from"]))
            $query->whereBetween('date', [$request->from, $request->to]);

        return $this->makeResponse($collection, $query->orderBy('date', 'asc')->get());
    }

    /**
     *  Returns names of the collections.
     *
     * @return \Illuminate\Support\Collection
     */
    public function collections()
    {
        return $this->collections;
    }

    /**
     *  Returns market symbols.
     *
     * @return \Illuminate\Support\Collection
     */
    public function symbols()
    {
        return $this->symbols;
    }

    /**
     * @param Request $request
     * @param $collection - Name of the collection to save to
     * @return Response|\Laravel\Lumen\Http\ResponseFactory
     * @throws ValidationException
     */
    public function save(Request $request, $collection)
    {
        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);

        $this->validate($request, [
            "date" => "required|date_format:Y-m-d",
            "close" => "required|numeric",
            "open" => "required|numeric",
            "low" => "required|numeric",
            "high" => "required|numeric",
        ], [
            "date_format" => "date format invalid. Format: 1999-01-01",
        ]);

        if (DB::collection($this->sourceSwitch($collection["collection"]))->where("date", $request->date)->first())
            return response("Record for given date ($request->date) already exists", 409);
        else {

            $date_pieces = explode("-", $request->date);

            $ack = DB::collection($this->sourceSwitch($collection["collection"]))->insert([
                "date" => $request->date,
                "date_pieces" => [
                    "year" => $date_pieces[0],
                    "month" => $date_pieces[1],
                    "day" => $date_pieces[2]
                ],
                "close" => $request->close,
                "high" => $request->high,
                "open" => $request->open,
                "low" => $request->low,
            ]);

            if ($ack)
                return response("Success", 201);
            else
                return response("Error saving data", 500);
        }
    }

    /**
     * Fetch the latest value for the given collection
     * @param $collection
     * @return Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function latest($collection)
    {
        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);

        $item = DB::collection($this->sourceSwitch($collection["collection"]))->orderBy('date', 'desc')->first();
        $item["name"] = $collection["collection"];
        $item["symbol"] = $collection["symbol"];
        return $item;
    }

    /**
     * Returns data for the given amount of days
     * @param Request $request
     * @param $collection
     * @return array|Response|\Laravel\Lumen\Http\ResponseFactory
     * @throws ValidationException
     */
    public function getByDayCount(Request $request, $collection)
    {

        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);


        $this->validate($request, [
            "days" => "required|integer|min:1",
        ], [
            "required" => "This field is required.",
        ]);

        $items = DB::collection($this->sourceSwitch($collection["collection"]))->orderBy('date', 'desc')->limit((int)$request->days)->get();

        return $this->makeResponse($collection, $items);
    }

    /**
     * inserts predictions to the database
     * @param Request $request
     * @param $collection
     * @return Response|\Laravel\Lumen\Http\ResponseFactory
     * @throws ValidationException
     */
    public function insertPrediction(Request $request, $collection)
    {

        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);

        $this->validate($request, [
            "date" => "required|date_format:Y-m-d",
            "close" => "required|numeric",
        ], [
            "date_format" => "date format invalid. Format: 1999-01-01",
        ]);

        if (DB::collection('predictions')->where("date", $request->date)->where('symbol', $collection['symbol'])->first())
            return response("Record for given date ($request->date) already exists", 409);
        else {

            $date_pieces = explode("-", $request->date);

            $ack = DB::collection('predictions')->insert([
                "date" => $request->date,
                "date_pieces" => [
                    "year" => $date_pieces[0],
                    "month" => $date_pieces[1],
                    "day" => $date_pieces[2]
                ],
                "close" => $request->close,
                "symbol" => $collection["symbol"],
                "name" => $collection["collection"]
            ]);

            if ($ack)
                return response("Success", 201);
            else
                return response("Error saving data", 500);
        }
    }

    /**
     * Returns predictions for the date range given
     * @param Request $request
     * @param $collection
     * @return array|Response|\Laravel\Lumen\Http\ResponseFactory
     * @throws ValidationException
     */
    public function getPredictionsByDate(Request $request, $collection)
    {
        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);

        $this->validate($request, [
            "from" => "sometimes|date_format:Y-m-d",
            "to" => "required_with:from|date_format:Y-m-d|after_or_equal:from"
        ], [
            "date_format" => "date format invalid. Format: 1999-01-01",
            "required" => "This field is required.",
            "required_with" => " 'To' is required with 'From' ",
            "after_or_equal" => " 'To' field must be after or equal to 'From' "
        ]);


        $query = DB::collection('predictions')->where('name', $collection["collection"]);

        if ($request->has(["to", "from"]))
            $query->whereBetween('date', [$request->from, $request->to]);

        return $this->makeResponse($collection, $query->orderBy('date', 'asc')->get());
    }

    /**
     * Returns predictions for the given amount of days
     * @param Request $request
     * @param $collection
     * @return array|Response|\Laravel\Lumen\Http\ResponseFactory
     * @throws ValidationException
     */
    public function getPredictionsByDayCount(Request $request, $collection)
    {

        $collection = $this->getInputInfo($collection);
        if (!$collection["found"])
            return response("Invalid Collection. Hit /collections for collection names or /symbols for market symbols", 422);


        $this->validate($request, [
            "days" => "required|integer|min:1",
        ], [
            "required" => "This field is required.",
        ]);

        $items = DB::collection('predictions')->where('name', $collection["collection"])->orderBy('date', 'desc')->limit((int)$request->days)->get();

        return $this->makeResponse($collection, $items);
    }
}