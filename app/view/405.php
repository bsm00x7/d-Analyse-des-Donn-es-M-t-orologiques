<?php
http_response_code(405);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>405 - Method Not Allowed</title>

    <style>
        body {
            margin: 0;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: #f5f5f5;
        }

        .box {
            text-align: center;
        }

        h1 {
            font-size: 80px;
            margin: 0;
            color: #d9534f;
        }

        p {
            font-size: 18px;
            color: #555;
        }

        a {
            display: inline-block;
            margin-top: 15px;
            text-decoration: none;
            padding: 10px 20px;
            background: #333;
            color: white;
            border-radius: 6px;
        }

        a:hover {
            background: #000;
        }
    </style>
</head>

<body>
    <div class="box">
        <h1>405</h1>
        <p>Method Not Allowed</p>
        <a href=" /analyseM/home">Go Home</a>
    </div>
</body>

</html>