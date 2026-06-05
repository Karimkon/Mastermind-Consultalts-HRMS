<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#1e293b;font-size:14px;line-height:1.6;background:#f8fafc;margin:0;padding:0;">
<div style="max-width:560px;margin:40px auto;background:#fff;border-radius:8px;overflow:hidden;border:1px solid #e2e8f0;">
    <div style="background:#1e40af;padding:24px 32px;">
        <h1 style="color:#fff;margin:0;font-size:20px;">Mastermind Consultants</h1>
        <p style="color:#93c5fd;margin:4px 0 0;font-size:13px;">Human Resource Management System</p>
    </div>
    <div style="padding:32px;">
        <p style="margin:0 0 16px;">Dear <strong>{{ $payslip->employee->full_name }}</strong>,</p>
        <p style="margin:0 0 16px;">
            Please find attached your payslip for
            <strong>{{ \Carbon\Carbon::createFromDate($payroll_run->year, $payroll_run->month, 1)->format('F Y') }}</strong>.
        </p>
        <div style="background:#f1f5f9;border-radius:6px;padding:16px 20px;margin:20px 0;">
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <tr>
                    <td style="padding:4px 0;color:#64748b;">Employee</td>
                    <td style="padding:4px 0;font-weight:600;text-align:right;">{{ $payslip->employee->emp_number }} — {{ $payslip->employee->full_name }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:#64748b;">Period</td>
                    <td style="padding:4px 0;font-weight:600;text-align:right;">{{ \Carbon\Carbon::createFromDate($payroll_run->year, $payroll_run->month, 1)->format('F Y') }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:#64748b;">Gross Pay</td>
                    <td style="padding:4px 0;font-weight:600;text-align:right;">UGX {{ number_format($payslip->gross_salary, 2) }}</td>
                </tr>
                <tr>
                    <td style="padding:4px 0;color:#64748b;">Net Pay</td>
                    <td style="padding:4px 0;font-weight:700;color:#16a34a;text-align:right;font-size:15px;">UGX {{ number_format($payslip->net_salary, 2) }}</td>
                </tr>
            </table>
        </div>
        <p style="margin:0 0 8px;font-size:13px;color:#64748b;">The full breakdown is in the attached PDF payslip.</p>
        <p style="margin:24px 0 0;font-size:13px;color:#64748b;">If you have any questions, please contact the HR department.</p>
        <p style="margin:16px 0 0;">Regards,<br><strong>Mastermind Consultants — HR Team</strong></p>
    </div>
    <div style="background:#f8fafc;padding:16px 32px;border-top:1px solid #e2e8f0;font-size:11px;color:#94a3b8;text-align:center;">
        This is an automated email. Please do not reply directly to this message.
    </div>
</div>
</body>
</html>
