<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function (){

    return [
        "get all collections" => "/collections",
        "get all symbols" => "/symbols",
        "get data (optional query string parameters: 'from' and 'to' )" => "/getData/{collection or symbol}",
        "get data by day count" => "/getDataByCount/{collection or symbol}?days=10 (min is 1)",
        "get latest entry" => "/latest/{collection or symbol}",
        "get predictions (optional query string parameters: 'from' and 'to' )" => "/predictionsByDate/{collection or symbol}",
        "get predictions by day count" => "/predictionsByCount/{collection or symbol}?days=10 (min is 1)",
        "[POST] insert item (date, high, low, open, close are required)" => "/insert/{collection or symbol}?date=(format = 2019-03-31)",
        "[POST] insert prediction (date, close are required)" => "/prediction/{collection or symbol}?date=(format = 2019-03-31)",
    ];
});
$router->get('getData/{collection}', 'MarketController@getCollection');
$router->get('collections', 'MarketController@collections');
$router->get('symbols', 'MarketController@symbols');
$router->post('insert/{collection}', 'MarketController@save');
$router->post('prediction/{collection}', 'MarketController@insertPrediction');
$router->get('latest/{collection}', 'MarketController@latest');
$router->get('getDataByCount/{collection}', 'MarketController@getByDayCount');
$router->get('predictionsByCount/{collection}', 'MarketController@getPredictionsByDayCount');
$router->get('predictionsByDate/{collection}', 'MarketController@getPredictionsByDate');
$router->get('info', function (){
    phpinfo();
});
