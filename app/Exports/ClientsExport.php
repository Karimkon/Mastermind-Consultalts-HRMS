<?php
namespace App\Exports;

use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ClientsExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function collection()
    {
        return Client::with(['user', 'accountManager'])->orderBy('company_name')->get();
    }

    public function headings(): array
    {
        return [
            'company_name', 'contact_person', 'login_email',
            'client_email', 'phone', 'industry', 'address',
            'deployment_area', 'work_area', 'status', 'notes',
            'payment_day', 'work_site_address',
            'work_site_lat', 'work_site_lng', 'geo_fence_radius',
            'account_manager',
        ];
    }

    public function map($client): array
    {
        return [
            $client->company_name,
            $client->contact_person,
            $client->user?->email ?? '',
            $client->email ?? '',
            $client->phone ?? '',
            $client->industry ?? '',
            $client->address ?? '',
            $client->deployment_area ?? '',
            $client->work_area ?? '',
            $client->status,
            $client->notes ?? '',
            $client->payment_day ?? '',
            $client->work_site_address ?? '',
            $client->work_site_lat ?? '',
            $client->work_site_lng ?? '',
            $client->geo_fence_radius ?? '',
            $client->accountManager?->name ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
