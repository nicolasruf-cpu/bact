<?php

namespace App\Modules\Configuration\Services;

use App\Modules\Configuration\Enums\AstMethod;
use App\Modules\Configuration\Models\AstPanel;
use App\Modules\Configuration\Models\BreakpointSet;
use Illuminate\Validation\ValidationException;

/**
 * Formato CSV de puntos de corte para cargar la edición del M100 del laboratorio (Q-C4).
 * Columnas: organism_group;antibiotic_code;method;susceptible;resistant
 * Separador ";" o ","; coma o punto decimal. Las filas sin valores se ignoran.
 * El sistema no trae valores: la plantilla sale vacía.
 */
class BreakpointCsv
{
    public const HEADER = ['organism_group', 'antibiotic_code', 'method', 'susceptible', 'resistant'];

    /**
     * @return list<array{line: int, organism_group: string, antibiotic_code: string, method: string, susceptible: string, resistant: string}>
     *
     * @throws ValidationException
     */
    public function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $lines = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        if ($lines === [] || trim($lines[0]) === '') {
            throw ValidationException::withMessages(['file' => 'El archivo está vacío.']);
        }

        $delimiter = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), str_getcsv($lines[0], $delimiter, '"', ''));
        if ($header !== self::HEADER) {
            throw ValidationException::withMessages(['file' => 'La primera fila tiene que ser: '.implode($delimiter, self::HEADER).'.']);
        }

        $rows = [];
        foreach (array_slice($lines, 1) as $index => $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = array_map(fn ($c) => trim((string) $c), str_getcsv($line, $delimiter, '"', ''));
            $cells = array_pad($cells, 5, '');
            if ($cells[3] === '' && $cells[4] === '') {
                continue;
            }
            $rows[] = [
                'line' => $index + 2,
                'organism_group' => $cells[0],
                'antibiotic_code' => $cells[1],
                'method' => $cells[2],
                'susceptible' => $cells[3],
                'resistant' => $cells[4],
            ];
        }

        return $rows;
    }

    /**
     * CSV con una fila por antibiótico de cada panel y método. Con un set, trae sus
     * valores (exportar); sin set, vacío (plantilla para completar).
     */
    public function export(?BreakpointSet $set): string
    {
        $values = [];
        if ($set !== null) {
            foreach ($set->breakpoints()->with('antibiotic')->get() as $bp) {
                $values[$bp->organism_group->value.'|'.$bp->antibiotic->code.'|'.$bp->method->value] = [$bp->susceptible_value, $bp->resistant_value];
            }
        }

        $out = [implode(';', self::HEADER)];
        $seen = [];
        foreach (AstPanel::query()->with('antibiotics')->orderBy('organism_group')->get() as $panel) {
            foreach ($panel->antibiotics as $antibiotic) {
                foreach (AstMethod::cases() as $method) {
                    $key = $panel->organism_group->value.'|'.$antibiotic->code.'|'.$method->value;
                    if (isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    [$s, $r] = $values[$key] ?? ['', ''];
                    $out[] = implode(';', [$panel->organism_group->value, $antibiotic->code, $method->value, $this->number($s), $this->number($r)]);
                    unset($values[$key]);
                }
            }
        }
        // Valores del set que no están en ningún panel.
        foreach ($values as $key => [$s, $r]) {
            $out[] = implode(';', [...explode('|', $key), $this->number($s), $this->number($r)]);
        }

        return implode("\r\n", $out)."\r\n";
    }

    private function number(string|int|float $value): string
    {
        if ($value === '') {
            return '';
        }

        return rtrim(rtrim(number_format((float) $value, 3, ',', ''), '0'), ',');
    }
}
