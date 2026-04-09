<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subjectLine }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827; line-height: 1.5;">
    <h2 style="margin: 0 0 12px 0;">{{ $subjectLine }}</h2>

    <p style="margin: 0 0 16px 0;">The following import notifications were generated for your account:</p>

    @foreach ($notifications as $notification)
        <div style="margin: 0 0 16px 0; padding: 12px; border: 1px solid #e5e7eb; border-radius: 6px;">
            <h3 style="margin: 0 0 8px 0; font-size: 16px;">{{ $notification->data['subject'] ?? 'Notification' }}</h3>
            <p style="margin: 0; white-space: pre-line;">{{ $notification->data['message'] ?? '' }}</p>
        </div>
    @endforeach

    <p style="margin: 16px 0 0 0; font-size: 12px; color: #6b7280;">
        You are receiving this email because notification emails are enabled in your profile settings.
    </p>

    @foreach ($trackingPixelUrls as $trackingPixelUrl)
        <img
            src="{{ $trackingPixelUrl }}"
            alt=""
            width="1"
            height="1"
            style="display:block; border:0; width:1px; height:1px;"
        >
    @endforeach
</body>
</html>
