<?php

namespace App\Services;

class SimplePdfReport
{
    /**
     * Render a compact, dependency-free PDF summary.
     *
     * @param  array<int, string>  $lines
     */
    public function render(array $lines): string
    {
        $newline = chr(10);
        $pages = array_chunk($lines, 48);

        if ($pages === []) {
            $pages = [['No report data']];
        }

        $objects = [];
        $pageIds = [];
        $fontId = 3;
        $nextId = 4;
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';

        foreach ($pages as $pageIndex => $pageLines) {
            $pageId = $nextId++;
            $contentId = $nextId++;
            $pageIds[] = $pageId.' 0 R';
            $stream = 'BT'.$newline.'/F1 10 Tf'.$newline.'50 790 Td'.$newline.'14 TL'.$newline;

            foreach ($pageLines as $line) {
                $stream .= '('.$this->escapeText($line).') Tj'.$newline.'T*'.$newline;
            }

            $stream .= 'ET'.$newline.'BT'.$newline.'/F1 8 Tf'.$newline.'500 25 Td'.$newline
                .'(Page '.($pageIndex + 1).' of '.count($pages).') Tj'.$newline.'ET'.$newline;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>".$newline.'stream'.$newline.$stream.'endstream';
            $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $pageIds).'] /Count '.count($pages).' >>';
        $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
        ksort($objects);

        $pdf = '%PDF-1.4'.$newline;
        $offsets = [0];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= $id.' 0 obj'.$newline.$object.$newline.'endobj'.$newline;
        }

        $xref = strlen($pdf);
        $count = max(array_keys($objects)) + 1;
        $pdf .= 'xref'.$newline.'0 '.$count.$newline.'0000000000 65535 f '.$newline;

        for ($id = 1; $id < $count; $id++) {
            $pdf .= sprintf('%010d 00000 n '.$newline, $offsets[$id] ?? 0);
        }

        return $pdf.'trailer'.$newline.'<< /Size '.$count.' /Root 1 0 R >>'.$newline.'startxref'.$newline.$xref.$newline.'%%EOF';
    }

    private function escapeText(string $value): string
    {
        $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: '';
        $value = preg_replace('/[^\x20-\x7E]/', '', $value) ?? '';

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $value);
    }
}
