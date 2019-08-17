<?php

// Load environment variables \\
$env_path = PROJPATH.'.env';
if (!file_exists($env_path))
  die("Environment file not found at $env_path");
$env = new Symfony\Component\Dotenv\Dotenv();
$env->load($env_path);
