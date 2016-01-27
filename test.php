<?php
include "TR_View.php";

TR_View::bindLoader(function($name) {
	return file_get_contents('tests/' . $name . '.html');
});
echo TR_View::factory('3')->render();

