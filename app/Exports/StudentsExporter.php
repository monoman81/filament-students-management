<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class StudentsExporter implements FromCollection, WithMapping, WithHeadings
{

    use Exportable;

    public function __construct(public Collection $records)
    {
    }

    public function collection(): Collection
    {
        return $this->records;
    }

    public function map($row): array {
        return [
            $row->name,
            $row->email,
            $row->class->name,
            $row->section->name
        ];
    }

    public function headings(): array {
        return [
            'Name',
            'Email',
            'Class',
            'Section'
        ];
    }
}
