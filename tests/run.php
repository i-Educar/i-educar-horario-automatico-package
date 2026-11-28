#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__.'/../src/Services/DiretivasHorario.php';
require __DIR__.'/../src/Services/DetectorChoque.php';
require __DIR__.'/../src/Services/GradeSemanal.php';

$ok=0; $fail=0;
$assert = static function(bool $c, string $m) use (&$ok,&$fail): void {
  echo ($c? '  OK  ':' FAIL ').$m."\n";
  $c ? $ok++ : $fail++;
};

$diretivas = [
  'bloquear_choque_professor' => true,
  'bloquear_choque_turma' => true,
  'bloquear_multi_escola_turno' => true,
];

$ch = \iEducar\Packages\BuritiHorarioAutomatico\Services\DetectorChoque::choques([
  ['professor_id'=>1,'dia'=>1,'inicio'=>'08:00','fim'=>'09:00'],
  ['professor_id'=>1,'dia'=>1,'inicio'=>'08:30','fim'=>'09:30'],
], $diretivas);
$assert(count($ch)===1, 'detecta choque');
$assert(\iEducar\Packages\BuritiHorarioAutomatico\Services\GradeSemanal::cargaMinutos([['dia'=>1,'componente'=>'MAT','inicio'=>'08:00','fim'=>'09:00']])===60, 'carga 60min');

echo "\nResultado: $ok ok, $fail falhas\n";
exit($fail>0?1:0);
