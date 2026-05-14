<?php
namespace App\Notifications;

use App\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class DocumentExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Employee $employee,
        public int      $documentCount,
        public int      $certCount
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $earliest = null;

        $allExpiry = collect();
        foreach ($this->employee->documents->whereNotNull('expiry_date') as $d) {
            $allExpiry->push($d->expiry_date);
        }
        foreach ($this->employee->certifications->whereNotNull('expiry_date') as $c) {
            $allExpiry->push($c->expiry_date);
        }
        if ($allExpiry->isNotEmpty()) {
            $earliest = $allExpiry->min()?->format('Y-m-d');
        }

        return [
            'type'            => 'document_expiry',
            'employee_id'     => $this->employee->id,
            'employee_name'   => $this->employee->full_name,
            'document_count'  => $this->documentCount,
            'cert_count'      => $this->certCount,
            'earliest_expiry' => $earliest,
            'message'         => "Employee {$this->employee->full_name} has {$this->documentCount} document(s) and {$this->certCount} certification(s) expiring within 30 days.",
            'url'             => route('employees.show', $this->employee),
        ];
    }
}
