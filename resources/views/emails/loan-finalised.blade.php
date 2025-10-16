<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Finalised Notification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            border-radius: 10px;
        }
        .header {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
        }
        .content h1 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .content p {
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 20px;
        }
        .content .loan-details {
            background-color: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .content .loan-details table {
            width: 100%;
            border-collapse: collapse;
        }
        .content .loan-details table th,
        .content .loan-details table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        .footer {
            background-color: #f1f1f1;
            text-align: center;
            padding: 10px;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Loan Application Finalised</h1>
        </div>
        <div class="content">
            <h1>Hello, {{ $mailData['recipientName'] }}</h1>
            <p>We are pleased to inform you that the loan application you participated in has been {{ $mailData['status'] }}.</p>
            <div class="loan-details">
                <h2>Loan Details</h2>
                <table>
                    <tr>
                        <th>Application ID</th>
                        <td>{{ $mailData['application']->id }}</td>
                    </tr>
                    <tr>
                        <th>Applicant Name</th>
                        <td>{{  $mailData['application']->client->name. '|'. $mailData['application']->client?->mobile }}</td>
                    </tr>
                    <tr>
                        <th>Loan Amount</th>
                        <td>${{ number_format( $mailData['application']->amount, 2) }}</td>
                    </tr>
                    <tr>
                        <th>Status</th>
                        <td>{{ ucfirst($mailData['status']) }}</td>
                    </tr>
                    <tr>
                        <th>Initiated By</th>
                        <td>{{ $mailData['application']->createdBy->name }}</td>
                    </tr>

                </table>
            </div>
            <p>Thank you for your participation in this loan process.</p>
            <p>Best regards,</p>
            <p>The Credit Scoring Team</p>
        </div>

    </div>
</body>
</html>
