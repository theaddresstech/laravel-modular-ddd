fix <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
    <link rel="stylesheet" type="text/css" href="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui.css" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.10.3/favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="https://unpkg.com/swagger-ui-dist@5.10.3/favicon-16x16.png" sizes="16x16" />
    <style>
        html {
            box-sizing: border-box;
            overflow: -moz-scrollbars-vertical;
            overflow-y: scroll;
        }

        *, *:before, *:after {
            box-sizing: inherit;
        }

        body {
            margin:0;
            background: #fafafa;
        }

        .swagger-ui .topbar {
            background-color: #1976d2;
            padding: 8px 0;
        }

        .swagger-ui .topbar .download-url-wrapper {
            display: none;
        }

        .swagger-ui .info {
            margin: 50px 0;
        }

        .swagger-ui .info .title {
            font-size: 36px;
            color: #3b4151;
        }

        .custom-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            text-align: center;
        }

        .custom-header h1 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 300;
        }

        .custom-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }

        .version-links {
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
            padding: 15px 0;
            text-align: center;
        }

        .version-links a {
            display: inline-block;
            margin: 0 10px;
            padding: 8px 16px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
            transition: background-color 0.3s;
        }

        .version-links a:hover {
            background: #0056b3;
        }

        .version-links a.active {
            background: #28a745;
        }

        .footer {
            background: #f8f9fa;
            border-top: 1px solid #e9ecef;
            padding: 20px 0;
            text-align: center;
            margin-top: 50px;
        }

        #swagger-ui {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }
    </style>
</head>
<body>
    <div class="custom-header">
        <h1>{{ $title }}</h1>
        <p>Laravel Modular DDD API Documentation</p>
    </div>

    <div class="version-links">
        <a href="/api/docs">All APIs</a>
        <a href="/api/docs/v1">Version 1</a>
        <a href="/api/docs/v2">Version 2</a>
        <a href="/api/versions">API Discovery</a>
        <a href="/api/openapi.json">OpenAPI Spec</a>
    </div>

    <div id="swagger-ui"></div>

    <div class="footer">
        <p>Generated with Laravel Modular DDD Package •
           <a href="https://github.com/theaddresstech/laravel-modular-ddd" target="_blank">Documentation</a> •
           <a href="/api/versions" target="_blank">API Discovery</a>
        </p>
    </div>

    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5.10.3/swagger-ui-standalone-preset.js"></script>
    <script>
        window.onload = function() {
            const spec = {!! $spec !!};

            // Build a system
            const ui = SwaggerUIBundle({
                spec: spec,
                dom_id: '#swagger-ui',
                deepLinking: true,
                presets: [
                    SwaggerUIBundle.presets.apis,
                    SwaggerUIStandalonePreset
                ],
                plugins: [
                    SwaggerUIBundle.plugins.DownloadUrl
                ],
                layout: "StandaloneLayout",
                validatorUrl: null,
                tryItOutEnabled: true,
                supportedSubmitMethods: ['get', 'post', 'put', 'delete', 'patch'],
                onComplete: function() {
                    console.log('Swagger UI loaded successfully');
                },
                requestInterceptor: function(request) {
                    // Add any default headers or authentication
                    return request;
                },
                responseInterceptor: function(response) {
                    return response;
                }
            });

            window.ui = ui;

            // Highlight current version in navigation
            const currentPath = window.location.pathname;
            const links = document.querySelectorAll('.version-links a');
            links.forEach(link => {
                if (link.getAttribute('href') === currentPath) {
                    link.classList.add('active');
                }
            });
        };
    </script>
</body>
</html>