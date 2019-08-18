<?php
require __DIR__.'/../../config/init/minimal.php';

use App\CoreUtils;

CoreUtils::generateApiSchema(CoreUtils::env('PRODUCTION'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>MLP Vector Club OpenAPI Documentation</title>
  <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@3/swagger-ui.css">
  <meta name="robots" content="noindex">
  <meta name="viewport" content="width=device-width,height=device-height,initial-scale=1,maximum-scale=2">
</head>

<body>
<div id="swagger-ui"></div>
<script src="https://unpkg.com/swagger-ui-dist@3/swagger-ui-bundle.js"></script>
<script type="text/javascript">
  window.onload = function() {
    SwaggerUIBundle({
      deepLinking: true,
      dom_id: '#swagger-ui',
      showExtensions: true,
      showCommonExtensions: true,
      url: <?= \App\JSON::encode('/'.API_SCHEMA_PATH) ?>,
    });
  };
</script>
</body>
</html>
