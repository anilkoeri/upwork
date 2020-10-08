<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class FormatCSV implements ToCollection
{

    public function collection(Collection $rows)
    {
        foreach ($rows as $row)
        {
            User::create([
                'name' => $row[0],
            ]);
        }
    }
}
