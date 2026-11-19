<?php
namespace App\Services;
class SimplePdfReport
{
    /** @param array<int,string> $lines */
    public function render(array $lines): string
    {
        $pages=array_chunk($lines,48); if($pages===[])$pages=[['No report data']];
        $objects=[]; $pageIds=[]; $fontId=3; $nextId=4; $objects[1]='<< /Type /Catalog /Pages 2 0 R >>';
        foreach($pages as $pageLines){
            $pageId=$nextId++; $contentId=$nextId++; $pageIds[]=$pageId.' 0 R';
            $stream="BT\n/F1 10 Tf\n50 790 Td\n14 TL\n";
            foreach($pageLines as $line)$stream.='('.$this->escapeText($line).") Tj\nT*\n";
            $stream.="ET\n";
            $objects[$contentId]='<< /Length '.strlen($stream)." >>\nstream\n".$stream."endstream";
            $objects[$pageId]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 '.$fontId.' 0 R >> >> /Contents '.$contentId.' 0 R >>';
        }
        $objects[2]='<< /Type /Pages /Kids ['.implode(' ',$pageIds).'] /Count '.count($pages).' >>';
        $objects[$fontId]='<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'; ksort($objects);
        $pdf="%PDF-1.4\n"; $offsets=[0];
        foreach($objects as $id=>$object){$offsets[$id]=strlen($pdf);$pdf.=$id." 0 obj\n".$object."\nendobj\n";}
        $xref=strlen($pdf);$count=max(array_keys($objects))+1;$pdf.="xref\n0 ".$count."\n0000000000 65535 f \n";
        for($id=1;$id<$count;$id++)$pdf.=sprintf("%010d 00000 n \n",$offsets[$id]??0);
        return $pdf."trailer\n<< /Size ".$count." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
    private function escapeText(string $value): string
    {
        $value=iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$value)?:'';
        $value=preg_replace('/[^\x20-\x7E]/','',$value)??'';
        return str_replace(['\\','(',')'],['\\\\','\\(','\\)'],$value);
    }
}
