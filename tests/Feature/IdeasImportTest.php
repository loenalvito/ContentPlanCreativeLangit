<?php
namespace Tests\Feature;
use App\Models\User;use Database\Seeders\DatabaseSeeder;use Illuminate\Foundation\Testing\RefreshDatabase;use Illuminate\Http\UploadedFile;use Tests\TestCase;use ZipArchive;
class IdeasImportTest extends TestCase{
 use RefreshDatabase;protected function setUp():void{parent::setUp();$this->seed(DatabaseSeeder::class);}
 public function test_preview_maps_rich_text_headings_and_ignores_formatted_empty_rows():void{
  $path=tempnam(sys_get_temp_dir(),'ideas').'.xlsx';$zip=new ZipArchive;$zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE);$zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/></Types>');
  $rows='<row r="1"><c r="A1" t="inlineStr"><is><r><t>Format</t></r></is></c><c r="B1" t="inlineStr"><is><r><t>Idea</t></r></is></c><c r="C1" t="inlineStr"><is><r><t>Pillar</t></r></is></c><c r="D1" t="inlineStr"><is><r><t>Series</t></r></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><r><t>Reels</t></r></is></c><c r="B2" t="inlineStr"><is><r><t>Mapped idea</t></r></is></c><c r="C2" t="inlineStr"><is><r><t>Insight / Education</t></r></is></c><c r="D2" t="inlineStr"><is><r><t>Tips</t></r></is></c></row>';
  for($line=3;$line<=998;$line++)$rows.='<row r="'.$line.'" s="1"><c r="A'.$line.'" s="1"/></row>';
  $zip->addFromString('xl/worksheets/sheet1.xml','<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$rows.'</sheetData></worksheet>');$zip->close();
  $file=new UploadedFile($path,'ideas.xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',null,true);$response=$this->actingAs(User::whereEmail('admin@kolabo.id')->firstOrFail())->post('/ideas-preview',['file'=>$file]);
  $response->assertOk()->assertViewHas('valid',1)->assertViewHas('rows',fn($rows)=>count($rows)===1)->assertSee('Mapped idea')->assertDontSee('996 Invalid');@unlink($path);
 }
 public function test_preview_rejects_missing_required_headings():void{$file=UploadedFile::fake()->createWithContent('ideas.csv',"Wrong,Headers\nValue,Value\n");$this->actingAs(User::whereEmail('admin@kolabo.id')->firstOrFail())->from('/ideas')->post('/ideas-preview',['file'=>$file])->assertRedirect('/ideas')->assertSessionHasErrors('file');}
}
