<?php
namespace App\Imports;

use App\Models\{Client, User};
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Illuminate\Support\Facades\Hash;

/**
 * Import clients from Excel. Matches on company_name (case-insensitive).
 * Updates existing clients; creates new ones if company_name not found.
 * login_email is used to create/update the linked User account.
 */
class ClientsImport implements ToModel, WithHeadingRow, SkipsOnError
{
    use SkipsErrors;

    private array $updatable = [
        'contact_person', 'phone', 'industry', 'address',
        'deployment_area', 'work_area', 'status', 'notes',
        'payment_day', 'work_site_address',
        'work_site_lat', 'work_site_lng', 'geo_fence_radius',
    ];

    public function model(array $row): ?Client
    {
        if (empty($row['company_name'])) return null;

        $companyName = trim($row['company_name']);

        // Find or create the login User
        $loginEmail = !empty($row['login_email']) ? trim($row['login_email']) : null;
        $user = null;
        if ($loginEmail) {
            $user = User::firstOrCreate(
                ['email' => $loginEmail],
                [
                    'name'     => $row['contact_person'] ?? $companyName,
                    'password' => Hash::make('Client@1234'), // default password
                    'status'   => 'active',
                ]
            );
            if (!$user->hasRole('client')) {
                $user->syncRoles('client');
            }
        }

        // Find existing client
        $client = Client::whereRaw('LOWER(company_name) = ?', [strtolower($companyName)])->first();

        $data = ['company_name' => $companyName];

        if ($user) $data['user_id'] = $user->id;

        if (!empty($row['client_email'])) $data['email'] = trim($row['client_email']);

        foreach ($this->updatable as $col) {
            if (isset($row[$col]) && $row[$col] !== '') {
                $data[$col] = trim((string)$row[$col]);
            }
        }

        if ($client) {
            $client->update($data);
            return null; // updateOrCreate not needed, we updated manually
        }

        if (!isset($data['status'])) $data['status'] = 'active';

        return new Client($data);
    }
}
