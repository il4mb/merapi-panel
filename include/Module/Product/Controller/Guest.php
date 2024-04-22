<?php
namespace MerapiPanel\Module\Product\Controller;

use MerapiPanel\Box\Module\__Controller;
use MerapiPanel\Views\View;
use MerapiPanel\Utility\Router;

class Guest extends __Controller
{

	function register()
	{
		Router::GET("/Product", "index", self::class);
		// register other route
	}
	function index()
	{
		return View::render("index.html.twig");
	}
}