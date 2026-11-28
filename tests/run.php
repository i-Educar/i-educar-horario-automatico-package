#!/usr/bin/env php
<?php
declare(strict_types=1);

require __DIR__.'/../src/Services/DiretivasHorario.php';
require __DIR__.'/../src/Services/DetectorChoque.php';
require __DIR__.'/../src/Services/GradeSemanal.php';
require __DIR__.'/../src/Services/TurmaPerfilHorario.php';

$ok=0; $fail=0;
$assert = static function(bool $c, string $m) use (&$ok,&$fail): void {
  echo ($c? '  OK  ':' FAIL ').$m."\n";
  $c ? $ok++ : $fail++;
};

$Aee = \iEducar\Packages\BuritiHorarioAutomatico\Services\TurmaPerfilHorario::class;
$assert($Aee::ehAeeOuEducacaoEspecial('AEE Manhã', 'AEE', '{5}') === true, 'detecta AEE por tipo_atendimento 5');
$assert($Aee::ehAeeOuEducacaoEspecial('1º Ano', 'Educação especial', null, 2) === true, 'detecta Educação especial por modalidade');
$assert($Aee::ehAeeOuEducacaoEspecial('Classe especial A', 'Ensino regular', null, 1, 1) === true, 'detecta classe_especial');
$assert($Aee::ehAeeOuEducacaoEspecial('3º Ano A', 'Ensino Fundamental', '{0}', 1, 0) === false, 'turma regular não é AEE');
$assert($Aee::normalizarTiposAtendimento('{0,5}') === [0, 5], 'normaliza array PG de tipos');

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
