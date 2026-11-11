<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Interview Scheduled</title>
</head>
<body>

<h2>Interview Scheduled</h2>

<p>Dear {{ $application->full_name }},</p>

<p>
    We are pleased to inform you that you have been selected
    for an interview for the training program.
</p>

<p>
    <strong>Date:</strong>
    {{ $interview->time->format('Y-m-d') }}
</p>

<p>
    <strong>Time:</strong>
    {{ $interview->time->format('H:i') }}
</p>

<p>
    <strong>Location:</strong>
    {{ $interview->location }}
</p>

<p>
    Please make sure to attend at the scheduled time.
</p>

<p>
    Best regards,<br>
    Syriatel Training Team
</p>

</body>
</html>
