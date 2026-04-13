<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;

class DocumentationController extends Controller
{
    public function pdf()
    {
        $pdf = Pdf::loadView('docs.system-documentation')
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'defaultFont'         => 'DejaVu Sans',
                'isRemoteEnabled'     => false,
                'isHtml5ParserEnabled'=> true,
                'dpi'                 => 150,
            ]);

        return $pdf->download('Mastermind-HRMS-System-Documentation.pdf');
    }
}
