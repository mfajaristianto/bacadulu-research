<?php

namespace Tests\Support;

use ZipArchive;

/**
 * Pembangun DOCX untuk fixture uji: berkas ditulis ke direktori sementara milik tes dan dihapus lewat cleanup().
 * Tidak memakai berkas nyata mana pun; fixture berbahaya (path traversal, makro, XXE, zip bomb) dibuat di sini dan hanya
 * dibaca oleh pembaca yang diuji, tidak pernah dieksekusi.
 */
class DocxFactory
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    /** @var list<string> */
    private static array $made = [];

    /**
     * @param  list<array<string, mixed>>  $blocks  ['text','style','num'=>[id,lvl],'bold','runs'=>[['text','bold']],'del','table'=>[[..]]]
     * @param  array<string, mixed>  $opts  extra (nama=>isi), document (XML mentah), no_document, content_types, rels
     */
    public static function make(array $blocks, array $opts = []): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dpword').'.docx';
        self::$made[] = $path;

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', $opts['content_types'] ?? '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');

        if (empty($opts['no_document'])) {
            $zip->addFromString('word/document.xml', $opts['document'] ?? self::document($blocks));
        }

        $zip->addFromString('word/styles.xml', self::styles());
        $zip->addFromString('word/numbering.xml', self::numbering());

        if (isset($opts['rels'])) {
            $zip->addFromString('word/_rels/document.xml.rels', $opts['rels']);
        }

        foreach ($opts['extra'] ?? [] as $name => $content) {
            $zip->addFromString($name, $content);
        }

        $zip->close();

        return $path;
    }

    public static function raw(string $bytes, string $ext = 'docx'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'dpraw').'.'.$ext;
        file_put_contents($path, $bytes);
        self::$made[] = $path;

        return $path;
    }

    public static function cleanup(): void
    {
        foreach (self::$made as $path) {
            @unlink($path);
            @unlink(preg_replace('/\.\w+$/', '', $path));
        }

        self::$made = [];
    }

    private static function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private static function run(string $text, bool $bold = false): string
    {
        return '<w:r>'.($bold ? '<w:rPr><w:b/></w:rPr>' : '').'<w:t xml:space="preserve">'.self::esc($text).'</w:t></w:r>';
    }

    public static function document(array $blocks): string
    {
        $body = '';

        foreach ($blocks as $b) {
            if (isset($b['table'])) {
                $body .= '<w:tbl>';

                foreach ($b['table'] as $row) {
                    $body .= '<w:tr>'.implode('', array_map(fn ($c) => '<w:tc><w:p>'.self::run($c).'</w:p></w:tc>', $row)).'</w:tr>';
                }

                $body .= '</w:tbl>';

                continue;
            }

            $ppr = '';

            if (isset($b['style'])) {
                $ppr .= '<w:pStyle w:val="'.$b['style'].'"/>';
            }

            if (isset($b['num'])) {
                $ppr .= '<w:numPr><w:ilvl w:val="'.$b['num'][1].'"/><w:numId w:val="'.$b['num'][0].'"/></w:numPr>';
            }

            $runs = isset($b['runs']) ? implode('', array_map(fn ($r) => self::run($r[0], $r[1] ?? false), $b['runs'])) : self::run($b['text'] ?? '', $b['bold'] ?? false);

            if (isset($b['del'])) {
                $runs .= '<w:del><w:r><w:delText xml:space="preserve">'.self::esc($b['del']).'</w:delText></w:r></w:del>';
            }

            $body .= '<w:p>'.($ppr ? '<w:pPr>'.$ppr.'</w:pPr>' : '').$runs.'</w:p>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="'.self::W.'"><w:body>'.$body.'</w:body></w:document>';
    }

    private static function styles(): string
    {
        $s = '';

        foreach (['Heading1' => 'heading 1', 'Heading2' => 'heading 2', 'Heading3' => 'heading 3', 'Title' => 'Title', 'ListParagraph' => 'List Paragraph'] as $id => $name) {
            $s .= '<w:style w:type="paragraph" w:styleId="'.$id.'"><w:name w:val="'.$name.'"/></w:style>';
        }

        return '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="'.self::W.'">'.$s.'</w:styles>';
    }

    /** numId 1 = desimal, 2 = bullet, 3 = desimal (instance terpisah). */
    private static function numbering(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><w:numbering xmlns:w="'.self::W.'">'
            .'<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:numFmt w:val="decimal"/></w:lvl><w:lvl w:ilvl="1"><w:numFmt w:val="lowerLetter"/></w:lvl></w:abstractNum>'
            .'<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl><w:lvl w:ilvl="1"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
            .'<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num><w:num w:numId="2"><w:abstractNumId w:val="1"/></w:num><w:num w:numId="3"><w:abstractNumId w:val="0"/></w:num>'
            .'</w:numbering>';
    }
}
