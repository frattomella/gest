<?php
// Script di test locale per simulare un endpoint API JSON.
header('Content-Type: application/json; charset=utf-8');
readfile(__DIR__ . '/sample-vehicles.json');
