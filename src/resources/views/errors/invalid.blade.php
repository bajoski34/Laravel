<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Error - Flutterwave</title>
    <style>
        /* Millik */
        @font-face {
            font-family: "Millik";
            src: url("https://cdn.jsdelivr.net/gh/Flutterwave/lift@main/resources/Millik/Millik.ttf") format("truetype");
            font-weight: 400;
            font-style: normal;
        }

        /* Moderat */
        @font-face {
            font-family: "Moderat";
            font-style: normal;
            font-weight: 300;
            src: url(https://cdn.jsdelivr.net/gh/Flutterwave/lift@main/resources/Moderat/Moderat-Light.ttf) format("truetype");
        }

        @font-face {
            font-family: "Moderat";
            font-style: normal;
            font-weight: 400;
            src: url(https://cdn.jsdelivr.net/gh/Flutterwave/lift@main/resources/Moderat/Moderat-Regular.ttf) format("truetype");
        }

        @font-face {
            font-family: "Moderat";
            font-style: normal;
            font-weight: 500;
            src: url(https://cdn.jsdelivr.net/gh/Flutterwave/lift@main/resources/Moderat/Moderat-Medium.ttf) format("truetype");
        }

        @font-face {
            font-family: "Moderat";
            font-style: normal;
            font-weight: 600;
            src: url(https://cdn.jsdelivr.net/gh/Flutterwave/lift@main/resources/Moderat/Moderat-Bold.ttf) format("truetype");
        }



        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            padding: 20px;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            width: 100%;
            max-width: 900px;
            background-color: #fff;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }

        p {
            font-size: 1rem;
            font-family: "Moderat";
            line-height: 1.6;
        }

        h1 {
            color: #ff9b00;
            font-size: 2.5rem;
            font-family: 'Millik';
            margin-bottom: 20px;
            text-align: center;
        }

        .error-details {
            background-color: #FAF0F5;
            padding: 15px;
            margin-top: 20px;
            border-left: 4px solid #F9C8C8;
        }

        a {
            text-decoration: none;
            color: #FF9B00;
            font-weight: 700;
        }

        pre {
            background-color: #090E29;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Moderat', Courier, monospace;
            border-radius: 5px;
            font-family: 'Courier New', Courier, monospace;
            overflow-x: auto;
        }

        .copy-btn {
            background-color: #3498db;
            color: white;
            padding: 8px 15px;
            font-size: 0.9rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            margin-left: 10px;
        }

        .copy-btn:hover {
            background-color: #2a3362;
        }

        .system-info {
            background-color: #f4f4f4;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
        }

        .error-details,
        .system-info {
            position: relative;
        }

        .copy-btn-stacktrace {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        .copy-btn-system {
            position: absolute;
            top: 15px;
            right: 15px;
        }

        /* Mobile-first responsive design */
        @media (max-width: 768px) {
            h1 {
                font-size: 2rem;
            }

            .container {
                padding: 15px;
            }

            p {
                font-size: 0.9rem;
            }

            .error-details {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 480px) {
            h1 {
                font-size: 1.6rem;
            }

            p {
                font-size: 0.8rem;
            }

            .error-details {
                font-size: 0.8rem;
            }
        }
    </style>
</head>

<body>

    <div class="container">
        <h1>Error: Something Went Wrong.</h1>
        <p>Oops, something went wrong while processing the payment with Flutterwave. Please check the error details below to help you debug.</p>

        <div id="stackTraceText" class="error-details">
            <strong>Error Message:</strong>
            <p>{{ $_GET['message'] ?? 'No error message available.' }}</p>

            <strong>File:</strong>
            <p>{{ $errorFile ?? 'No file information.' }}</p>

            <strong>Line:</strong>
            <p>{{ $errorLine ?? 'No line number available.' }}</p>

            <strong>Stack Trace:</strong>
            <pre>{{ $stackTrace ?? 'No stack trace available.' }}</pre>
            <button class="copy-btn copy-btn-stacktrace" onclick="copyText('stackTraceText')">Copy Stack Trace</button>
        </div>

        <!-- System Information Section -->
        <div id="systemInfoText" class="system-info">
            <h2>System Information</h2>
            <p><strong>PHP Version:</strong> {{ PHP_VERSION }}</p>
            <p><strong>Laravel Version:</strong> {{ \Illuminate\Foundation\Application::VERSION }}</p>
            <p><strong>Flutterwave x Laravel Package Version:</strong> {{ \Flutterwave\Payments\Flutterwave::VERSION ?? '1.0.3' }}</p>
            <button class="copy-btn copy-btn-system" onclick="copyText('systemInfoText')">Copy Info</button>
        </div>

        <p>If you continue to experience issues, please <a href="https://flutterwave.com/gb/support/submit-request">contact support </a> with the above details.</p>
    </div>
    <script>
        function copyText(elementId) {
            const textToCopy = document.getElementById(elementId).innerText || document.getElementById(elementId).textContent;
            const textarea = document.createElement('textarea');
            textarea.value = textToCopy;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);

            // Change button text to "Copied!" after copying
            const button = event.target;
            button.textContent = 'Copied!';
            setTimeout(() => {
                button.textContent = 'Copy Info'; // Or "Copy Stack Trace" depending on the button
            }, 2000); // Reset the button text after 2 seconds
        }
    </script>
</body>

</html>