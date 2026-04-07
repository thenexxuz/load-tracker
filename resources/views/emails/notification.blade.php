<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin: 0 0 12px 0;">{{ $subjectLine }}</h2>

    <p style="margin: 0 0 16px 0; white-space: pre-line;">{{ $messageBody }}</p>

    <p style="margin: 16px 0 0 0; font-size: 12px; color: #6b7280;">
        You are receiving this email because notification emails are enabled in your profile settings.
    </p>

    <img
        src="{{ $trackingPixelUrl }}"
        alt=""
        width="1"
        height="1"
        style="display:block; border:0; width:1px; height:1px;"
    >
</body>
</html>
