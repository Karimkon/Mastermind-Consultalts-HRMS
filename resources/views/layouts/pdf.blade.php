<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>@yield("title","Document")</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 11px; color: #1e293b; }
        .header { background: #1e40af; color: white; padding: 20px; }
        .header h1 { font-size: 18px; font-weight: bold; }
        .header p { font-size: 11px; opacity: 0.8; margin-top: 2px; }
        .content { padding: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f1f5f9; text-align: left; padding: 8px 10px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background: #eff6ff; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 20px; font-size: 10px; }
    </style>
</head>
<body>
@yield("content")
</body>
</html>
