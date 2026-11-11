<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Application Decision</title>
</head>
<body>
<h2>Hello {{ $application->full_name }},</h2>

@if ($accepted)
    <p>
        Congratulations! Your application for the
        Syriatel training program has been accepted.
    </p>

    <p>
        Further instructions will be shared with you soon.
    </p>
@else
    <p>
        Thank you for applying to the Syriatel training program.
        Unfortunately, your application has not been accepted.
    </p>

    <p>We appreciate your interest and wish you success.</p>
@endif

<p>
    Best regards,<br>
    Syriatel Training Team
</p>
</body>
</html>
