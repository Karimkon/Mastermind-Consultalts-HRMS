<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy — Mastermind Consultants HRMS</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 text-gray-800">

{{-- Header --}}
<header class="bg-white border-b border-gray-200 sticky top-0 z-10 shadow-sm">
    <div class="max-w-4xl mx-auto px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-600 flex items-center justify-center">
                <span class="text-white font-bold text-lg">M</span>
            </div>
            <div>
                <p class="font-bold text-gray-900 text-sm leading-tight">Mastermind Consultants</p>
                <p class="text-xs text-gray-500">HRMS Mobile Application</p>
            </div>
        </div>
        <a href="{{ route('careers.index') }}" class="text-sm text-blue-600 hover:underline">View Jobs →</a>
    </div>
</header>

<main class="max-w-4xl mx-auto px-6 py-12">

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-12">

        <div class="mb-10">
            <h1 class="text-3xl font-bold text-gray-900 mb-3">Privacy Policy</h1>
            <p class="text-sm text-gray-500">
                <strong>App:</strong> Mastermind Consultants HRMS &nbsp;|&nbsp;
                <strong>Package:</strong> com.mastermind.consultants.hrms &nbsp;|&nbsp;
                <strong>Effective:</strong> {{ date('d F Y') }} &nbsp;|&nbsp;
                <strong>Last updated:</strong> {{ date('d F Y') }}
            </p>
        </div>

        <div class="prose prose-gray max-w-none space-y-8 text-sm leading-7">

            {{-- 1. Introduction --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">1. Introduction</h2>
                <p>
                    Mastermind Consultants ("we", "our", or "us") operates the <strong>Mastermind Consultants HRMS</strong>
                    mobile application (the "App"). This Privacy Policy explains how we collect, use, disclose,
                    and protect personal information when you use the App.
                </p>
                <p class="mt-3">
                    By downloading or using the App, you agree to the collection and use of information in
                    accordance with this policy. If you do not agree, please do not use the App.
                </p>
            </section>

            {{-- 2. Who Uses the App --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">2. Who Uses This App</h2>
                <p>The App is an internal Human Resources Management System used exclusively by:</p>
                <ul class="list-disc list-inside mt-2 space-y-1 text-gray-700">
                    <li>Employees of Mastermind Consultants</li>
                    <li>HR Administrators and Managers</li>
                    <li>Recruiters and Payroll Officers</li>
                    <li>Client companies with assigned staff</li>
                </ul>
                <p class="mt-3">
                    Access requires valid login credentials issued by Mastermind Consultants.
                    The App is <strong>not open to the general public</strong>.
                </p>
            </section>

            {{-- 3. Information We Collect --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">3. Information We Collect</h2>

                <h3 class="font-semibold text-gray-800 mt-4 mb-2">3.1 Information You Provide</h3>
                <ul class="list-disc list-inside space-y-1 text-gray-700">
                    <li>Full name, email address, and phone number</li>
                    <li>Employment details (job title, department, salary information)</li>
                    <li>Attendance records (clock-in/clock-out times)</li>
                    <li>Leave requests and balances</li>
                    <li>Performance reviews and goals</li>
                    <li>Training enrolments and certifications</li>
                    <li>Documents uploaded (payslips, contracts, identity documents)</li>
                    <li>Next-of-kin information</li>
                </ul>

                <h3 class="font-semibold text-gray-800 mt-4 mb-2">3.2 Information Collected Automatically</h3>
                <ul class="list-disc list-inside space-y-1 text-gray-700">
                    <li><strong>Location data:</strong> Approximate GPS coordinates are collected only when you clock in or clock out, to verify attendance location. Location is not tracked continuously.</li>
                    <li><strong>Device information:</strong> Device type and operating system version, used for technical support purposes.</li>
                    <li><strong>Usage logs:</strong> Actions performed within the App (e.g., leave requests submitted, documents accessed) are stored as audit logs for security and compliance.</li>
                </ul>
            </section>

            {{-- 4. How We Use Your Information --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">4. How We Use Your Information</h2>
                <p>We use the information collected for the following purposes:</p>
                <ul class="list-disc list-inside mt-2 space-y-1 text-gray-700">
                    <li>To authenticate your identity and provide access to the App</li>
                    <li>To manage HR processes — payroll, attendance, leave, performance, and recruitment</li>
                    <li>To verify attendance via geolocation during clock-in/out</li>
                    <li>To send notifications about leave approvals, payslips, and announcements</li>
                    <li>To generate HR reports for management</li>
                    <li>To maintain audit trails for compliance and security</li>
                    <li>To improve the App's functionality</li>
                </ul>
            </section>

            {{-- 5. Location Data --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">5. Location Data</h2>
                <p>
                    The App requests access to your device's location (<code>ACCESS_FINE_LOCATION</code>,
                    <code>ACCESS_COARSE_LOCATION</code>) <strong>only</strong> for the purpose of verifying
                    your physical location when you clock in or clock out of work.
                </p>
                <ul class="list-disc list-inside mt-3 space-y-1 text-gray-700">
                    <li>Location is accessed <strong>only when you initiate a clock-in or clock-out action</strong></li>
                    <li>Location is <strong>not tracked continuously</strong> or in the background</li>
                    <li>Location coordinates are stored in our secure server and are accessible only to HR Administrators</li>
                    <li>You may deny location permission; however, geofence attendance verification will not be available</li>
                </ul>
            </section>

            {{-- 6. Data Storage and Security --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">6. Data Storage &amp; Security</h2>
                <p>All data is stored on secure servers hosted at <strong>mastermind.autos</strong> (managed by Mastermind Consultants).</p>
                <ul class="list-disc list-inside mt-3 space-y-1 text-gray-700">
                    <li>All data is transmitted over <strong>HTTPS (TLS encryption)</strong></li>
                    <li>Authentication tokens are stored in the device's secure storage (Android Keystore)</li>
                    <li>Passwords are never stored in plain text — they are hashed using bcrypt</li>
                    <li>Access to data is restricted by role-based permissions</li>
                    <li>Two-factor authentication (MFA) is available for additional security</li>
                </ul>
                <p class="mt-3">
                    While we implement industry-standard security measures, no method of electronic storage
                    is 100% secure. We cannot guarantee absolute security.
                </p>
            </section>

            {{-- 7. Data Sharing --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">7. Data Sharing &amp; Disclosure</h2>
                <p>We do <strong>not</strong> sell, trade, or rent your personal information to third parties.</p>
                <p class="mt-3">Data may be shared in the following limited circumstances:</p>
                <ul class="list-disc list-inside mt-2 space-y-1 text-gray-700">
                    <li><strong>Client companies:</strong> If you are placed at a client site, that client may have limited access to your attendance and leave records relevant to their contract</li>
                    <li><strong>Legal requirements:</strong> If required by law, court order, or government authority</li>
                    <li><strong>Payroll processing:</strong> Salary and banking details are shared only with authorised payroll officers within Mastermind Consultants</li>
                </ul>
            </section>

            {{-- 8. Data Retention --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">8. Data Retention</h2>
                <p>
                    We retain your personal data for as long as your employment or engagement with Mastermind Consultants
                    is active, and for a period of <strong>5 years</strong> thereafter, as required by applicable
                    labour and tax laws in Uganda. After this period, data is securely deleted.
                </p>
            </section>

            {{-- 9. Your Rights --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">9. Your Rights</h2>
                <p>Depending on applicable law, you may have the right to:</p>
                <ul class="list-disc list-inside mt-2 space-y-1 text-gray-700">
                    <li><strong>Access</strong> the personal data we hold about you</li>
                    <li><strong>Correct</strong> inaccurate or incomplete data</li>
                    <li><strong>Delete</strong> your data (subject to legal retention requirements)</li>
                    <li><strong>Object</strong> to certain processing of your data</li>
                    <li><strong>Withdraw consent</strong> where processing is based on consent</li>
                </ul>
                <p class="mt-3">
                    To exercise these rights, contact your HR Administrator or email us at
                    <a href="mailto:privacy@mastermind.co.za" class="text-blue-600 hover:underline">privacy@mastermind.co.za</a>.
                </p>
            </section>

            {{-- 10. Children's Privacy --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">10. Children's Privacy</h2>
                <p>
                    The App is intended for use by adults (18+) who are employees or authorised users of
                    Mastermind Consultants. We do not knowingly collect personal data from children under 18.
                </p>
            </section>

            {{-- 11. Third-Party Services --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">11. Third-Party Services</h2>
                <p>The App does not integrate with third-party advertising networks or analytics SDKs. It communicates exclusively with the Mastermind Consultants backend API at <code>mastermind.autos</code>.</p>
            </section>

            {{-- 12. Changes to This Policy --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">12. Changes to This Privacy Policy</h2>
                <p>
                    We may update this Privacy Policy from time to time. When we do, we will update the
                    "Last updated" date at the top of this page. Continued use of the App after changes
                    constitutes acceptance of the updated policy.
                </p>
            </section>

            {{-- 13. Contact --}}
            <section>
                <h2 class="text-xl font-semibold text-gray-900 mb-3">13. Contact Us</h2>
                <p>If you have questions or concerns about this Privacy Policy, please contact:</p>
                <div class="mt-3 bg-blue-50 border border-blue-100 rounded-xl p-5 text-sm space-y-1">
                    <p><strong>Mastermind Consultants</strong></p>
                    <p>Data Controller &amp; HR System Administrator</p>
                    <p>Email: <a href="mailto:privacy@mastermind.co.za" class="text-blue-600 hover:underline">privacy@mastermind.co.za</a></p>
                    <p>Website: <a href="https://mastermind.autos" class="text-blue-600 hover:underline">https://mastermind.autos</a></p>
                </div>
            </section>

        </div>

        <div class="mt-10 pt-6 border-t border-gray-100 text-center text-xs text-gray-400">
            &copy; {{ date('Y') }} Mastermind Consultants. All rights reserved. &nbsp;|&nbsp;
            <a href="{{ route('careers.index') }}" class="hover:underline">Job Board</a> &nbsp;|&nbsp;
            <a href="{{ route('privacy') }}" class="hover:underline">Privacy Policy</a>
        </div>

    </div>

</main>

</body>
</html>
