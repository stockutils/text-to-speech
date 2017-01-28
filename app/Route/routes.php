<?php

/** @var Router $router */
use Minute\Model\Permission;
use Minute\Routing\Router;

/** @var Router $router */
$router->post('/tts/voices', 'TTS/Voices.php', false);
$router->post('/tts/generate', 'TTS/Generate.php', false);