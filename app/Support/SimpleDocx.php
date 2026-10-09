<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * A minimal Word (.docx) writer built straight from OOXML, for notes that
 * are pasted into financial statements. It does headings, paragraphs and
 * real Word tables (fixed column widths, right-aligned figures, single and
 * double rules), a running header and a footer with page numbers.
 * No package needed: a .docx is a zip of XML parts.
 *
 *   $doc = (new SimpleDocx('Title'))->header('Company name', 'IFRS 9 note')->footer('Prepared ...');
 *   $doc->heading('Note 12', 1)->paragraph('Text');
 *   $doc->table([['width' => 3600, 'align' => 'left'], ['width' => 1400]], [
 *       ['cells' => ['Opening', '1,234'], 'bold' => true],
 *       ['cells' => ['Closing', '2,345'], 'bold' => true, 'top' => 'single', 'bottom' => 'double'],
 *   ], ['cells' => ['', 'Stage 1']]);
 *   $doc->save($path);
 */
class SimpleDocx
{
    public const MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    private array $body = [];
    private string $headerLeft = '';
    private string $headerRight = '';
    private string $footer = '';

    public function __construct(private string $title, private string $font = 'Arial', private int $size = 18)
    {
    }

    public function header(string $left, string $right = ''): self
    {
        $this->headerLeft = $left;
        $this->headerRight = $right;

        return $this;
    }

    public function footer(string $text): self
    {
        $this->footer = $text;

        return $this;
    }

    public function heading(string $text, int $level = 1): self
    {
        $size = [1 => 26, 2 => 21, 3 => 19][$level] ?? 19;
        $this->body[] = $this->p([['text' => $text, 'bold' => true, 'size' => $size]], ['before' => $level === 1 ? 0 : 200, 'after' => 60, 'keepNext' => true]);

        return $this;
    }

    /** @param array{italic?:bool,bold?:bool,size?:int,color?:string,align?:string,after?:int} $opts */
    public function paragraph(string $text, array $opts = []): self
    {
        $this->body[] = $this->p([['text' => $text] + $opts], ['align' => $opts['align'] ?? 'both', 'after' => $opts['after'] ?? 100]);

        return $this;
    }

    public function pageBreak(): self
    {
        $this->body[] = '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';

        return $this;
    }

    /**
     * @param array<int,array{width:int,align?:string}> $columns widths in twips (1/1440 inch)
     * @param array<int,array{cells:array,bold?:bool,italic?:bool,top?:string,bottom?:string,color?:string,span?:bool}> $rows
     * @param array|null $head header row (bold, rule under it); a second header row may be passed as $head['sub']
     */
    public function table(array $columns, array $rows, ?array $head = null): self
    {
        $grid = implode('', array_map(fn ($c) => '<w:gridCol w:w="' . (int) $c['width'] . '"/>', $columns));
        $total = array_sum(array_column($columns, 'width'));
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . $total . '" w:type="dxa"/><w:tblLayout w:type="fixed"/>'
            . '<w:tblCellMar><w:left w:w="60" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar></w:tblPr>'
            . '<w:tblGrid>' . $grid . '</w:tblGrid>';

        if ($head) {
            $xml .= $this->row($columns, ['cells' => $head['cells'], 'bold' => true, 'bottom' => isset($head['sub']) ? null : 'single', 'header' => true]);
            if (isset($head['sub'])) {
                $xml .= $this->row($columns, ['cells' => $head['sub'], 'italic' => true, 'size' => 14, 'color' => '4B5563', 'bottom' => 'single', 'header' => true]);
            }
        }
        foreach ($rows as $r) {
            $xml .= $this->row($columns, $r);
        }
        $xml .= '</w:tbl>';
        $this->body[] = $xml;
        $this->body[] = $this->p([['text' => '']], ['after' => 60]);

        return $this;
    }

    public function save(string $path): void
    {
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Word file.');
        }
        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rootRels());
        $zip->addFromString('docProps/core.xml', $this->core());
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>MAIIC IFRS 9</Application></Properties>');
        $zip->addFromString('word/_rels/document.xml.rels', $this->docRels());
        $zip->addFromString('word/styles.xml', $this->styles());
        $zip->addFromString('word/settings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:defaultTabStop w:val="720"/><w:compat><w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/></w:compat></w:settings>');
        $zip->addFromString('word/header1.xml', $this->headerXml());
        $zip->addFromString('word/footer1.xml', $this->footerXml());
        $zip->addFromString('word/document.xml', $this->document());
        $zip->close();
    }

    // -----------------------------------------------------------------

    private function row(array $columns, array $r): string
    {
        $xml = '<w:tr>' . (!empty($r['header']) ? '<w:trPr><w:tblHeader/><w:cantSplit/></w:trPr>' : '<w:trPr><w:cantSplit/></w:trPr>');
        $cells = array_values($r['cells']);
        if (!empty($r['span'])) {
            $cells = [$cells[0]];
        }
        foreach ($cells as $i => $text) {
            $col = $columns[$i];
            $width = !empty($r['span']) ? array_sum(array_column($columns, 'width')) : (int) $col['width'];
            $borders = '';
            foreach (['top' => $r['top'] ?? null, 'bottom' => $r['bottom'] ?? null] as $side => $style) {
                if ($style && ($i > 0 || !empty($r['ruleLabel']))) {
                    $sz = $style === 'double' ? 6 : 6;
                    $borders .= '<w:' . $side . ' w:val="' . $style . '" w:sz="' . $sz . '" w:space="0" w:color="000000"/>';
                }
            }
            $tcPr = '<w:tcW w:w="' . $width . '" w:type="dxa"/>'
                . (!empty($r['span']) ? '<w:gridSpan w:val="' . count($columns) . '"/>' : '')
                . ($borders ? '<w:tcBorders>' . $borders . '</w:tcBorders>' : '')
                . '<w:vAlign w:val="bottom"/>';
            $run = ['text' => (string) $text, 'bold' => !empty($r['bold']), 'italic' => !empty($r['italic']), 'size' => $r['size'] ?? null, 'color' => $r['color'] ?? null];
            $align = !empty($r['span']) ? 'left' : ($col['align'] ?? 'right');
            $xml .= '<w:tc><w:tcPr>' . $tcPr . '</w:tcPr>' . $this->p([$run], ['align' => $align, 'after' => 0, 'before' => 20]) . '</w:tc>';
        }

        return $xml . '</w:tr>';
    }

    /** @param array<int,array{text:string,bold?:bool,italic?:bool,size?:int,color?:string}> $runs */
    private function p(array $runs, array $opts = []): string
    {
        $align = ['left' => 'left', 'right' => 'right', 'center' => 'center', 'both' => 'both'][$opts['align'] ?? 'left'] ?? 'left';
        $ppr = '<w:pPr>' . (!empty($opts['keepNext']) ? '<w:keepNext/>' : '')
            . '<w:spacing w:before="' . (int) ($opts['before'] ?? 0) . '" w:after="' . (int) ($opts['after'] ?? 0) . '"/>'
            . '<w:jc w:val="' . $align . '"/></w:pPr>';
        $xml = '<w:p>' . $ppr;
        foreach ($runs as $run) {
            $rpr = (!empty($run['bold']) ? '<w:b/>' : '') . (!empty($run['italic']) ? '<w:i/>' : '')
                . (!empty($run['color']) ? '<w:color w:val="' . $run['color'] . '"/>' : '')
                . (!empty($run['size']) ? '<w:sz w:val="' . (int) $run['size'] . '"/><w:szCs w:val="' . (int) $run['size'] . '"/>' : '');
            $xml .= '<w:r>' . ($rpr ? '<w:rPr>' . $rpr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . self::esc($run['text']) . '</w:t></w:r>';
        }

        return $xml . '</w:p>';
    }

    private function document(): string
    {
        // A4 portrait, 2 cm margins.
        $sect = '<w:sectPr><w:headerReference w:type="default" r:id="rIdHeader"/><w:footerReference w:type="default" r:id="rIdFooter"/>'
            . '<w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1300" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<w:body>' . implode('', $this->body) . $sect . '</w:body></w:document>';
    }

    private function headerXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr>'
            . '<w:pBdr><w:bottom w:val="single" w:sz="12" w:space="2" w:color="3FA535"/></w:pBdr>'
            . '<w:tabs><w:tab w:val="right" w:pos="9638"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
            . '<w:r><w:rPr><w:b/><w:color w:val="22591D"/><w:sz w:val="18"/></w:rPr><w:t xml:space="preserve">' . self::esc($this->headerLeft) . '</w:t></w:r>'
            . '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="16"/></w:rPr><w:tab/><w:t xml:space="preserve">' . self::esc($this->headerRight) . '</w:t></w:r>'
            . '</w:p></w:hdr>';
    }

    private function footerXml(): string
    {
        $run = fn ($t) => '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="14"/></w:rPr><w:t xml:space="preserve">' . self::esc($t) . '</w:t></w:r>';
        $field = fn ($f) => '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="14"/></w:rPr><w:fldChar w:fldCharType="begin"/></w:r>'
            . '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="14"/></w:rPr><w:instrText xml:space="preserve"> ' . $f . ' </w:instrText></w:r>'
            . '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="14"/></w:rPr><w:fldChar w:fldCharType="separate"/></w:r>' . $run('1')
            . '<w:r><w:rPr><w:color w:val="6B7280"/><w:sz w:val="14"/></w:rPr><w:fldChar w:fldCharType="end"/></w:r>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:pPr>'
            . '<w:pBdr><w:top w:val="single" w:sz="4" w:space="2" w:color="9CA3AF"/></w:pBdr>'
            . '<w:tabs><w:tab w:val="right" w:pos="9638"/></w:tabs><w:spacing w:after="0"/></w:pPr>'
            . $run($this->footer) . '<w:r><w:tab/></w:r>' . $run('Page ') . $field('PAGE') . $run(' of ') . $field('NUMPAGES')
            . '</w:p></w:ftr>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="' . $this->font . '" w:hAnsi="' . $this->font . '" w:cs="' . $this->font . '"/>'
            . '<w:sz w:val="' . $this->size . '"/><w:szCs w:val="' . $this->size . '"/><w:lang w:val="en-GB"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="0" w:line="252" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
            . '<w:style w:type="table" w:default="1" w:styleId="TableNormal"><w:name w:val="Normal Table"/><w:tblPr><w:tblInd w:w="0" w:type="dxa"/>'
            . '<w:tblCellMar><w:top w:w="0" w:type="dxa"/><w:left w:w="60" w:type="dxa"/><w:bottom w:w="0" w:type="dxa"/><w:right w:w="60" w:type="dxa"/></w:tblCellMar></w:tblPr></w:style>'
            . '</w:styles>';
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '<Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>'
            . '<Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>'
            . '<Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            . '</Relationships>';
    }

    private function docRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '<Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>'
            . '<Relationship Id="rIdHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>'
            . '<Relationship Id="rIdFooter" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>'
            . '</Relationships>';
    }

    private function core(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:title>' . self::esc($this->title) . '</dc:title><dc:creator>MAIIC IFRS 9</dc:creator>'
            . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created><dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
            . '</cp:coreProperties>';
    }

    private static function esc(string $text): string
    {
        $text = str_replace(["\u{2014}", "\u{2013}"], '-', $text);

        return htmlspecialchars((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $text), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
