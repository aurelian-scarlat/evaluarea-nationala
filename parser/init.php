<?php

/*
  Script pentru parsat sursele de date si puse intr-o baza de date SQLite
  --
  Descarcat lista scolilor de la https://data.gov.ro/dataset/reteaua-scolara-2022-2023/resource/49b9a5d3-361d-48c7-92b0-19b6288adc1f
  Salvat din Excel ca "CSV UTF-8"
  --
  Windows:
    docker run -it --rm --name parser-evaluare -v %cd%:/usr/src/parser -w /usr/src/parser php:8.2-cli php init.php
  Linux
    docker run -it --rm --name parser-evaluare -v $PWD:/usr/src/parser -w /usr/src/parser php:8.2-cli php init.php
  --
  in sqlite interogarile astea sunt slow af, asa ca trebuie facute de mana
  UPDATE scoli SET prezenti = (SELECT COUNT(*) FROM note WHERE scoala = scoli.cod AND medie > 0);
  UPDATE scoli SET absenti = (SELECT COUNT(*) FROM note WHERE scoala = scoli.cod AND medie < 0);
*/

$anul = 2023;

$jud = ["ab", "ag", "ar", "b", "bc", "bh", "bn", "br", "bt", "bv", "bz", "cj", "cl", "cs", "ct", "cv", "db", "dj", "gj", "gl", "gr", "hd", "hr", "if", "il", "is", "mh", "mm", "ms", "nt", "ot", "ph", "sb", "sj", "sm", "sv", "tl", "tm", "tr", "vl", "vn", "vs"];

try{
    $db = new PDO("sqlite:" . $anul . "/db.sqlite");
} catch(PDOException $e) {
    die($e->getMessage());
}

$db->exec("DROP TABLE IF EXISTS note");
$db->exec("DROP TABLE IF EXISTS scoli");

$db->exec("CREATE TABLE scoli (
    cod INTEGER PRIMARY KEY,
    judet TEXT,
    localitate TEXT,
    sat TEXT,
    mediu TEXT,
    tip TEXT,
    nume TEXT,
    prezenti INTEGER,
    absenti INTEGER
  )");
$db->exec("CREATE TABLE note (
    cod TEXT PRIMARY KEY,
    scoala INTEGER,
    judet TEXT,
    romana REAL,
    matematica REAL,
    materna REAL,
    medie REAL,
    init_romana REAL,
    init_matematica REAL,
    init_materna REAL,
    FOREIGN KEY (scoala) REFERENCES scoli(cod)
  )");

$start = microtime(true);
$file = fopen($anul . "/scoli.csv", "r");
if(!$file) {
    die("Fisierul CSV cu scolile nu exista sau nu poate fi deschis");
}

// sarim peste primele 4 linii
for($i = 0; $i < 4; $i++){
    fgets($file);
}

$db->beginTransaction();

$insert = $db->prepare("INSERT INTO scoli (cod, judet, nume, tip, mediu, localitate, sat) values (:cod, :judet, :nume, :tip, :mediu, :localitate, :sat)");

while(($scoala = fgetcsv($file)) !== false) {
    if(substr($scoala[2], 0, 10) == "BUCUREŞTI") {
        $scoala[2] = $scoala[7] = substr($scoala[2], 11);
    }

    $insert->execute([
        "cod" => $scoala[11],
        "judet" => $scoala[1],
        "localitate" => $scoala[2],
        "sat" => $scoala[7],
        "mediu" => $scoala[9],
        "tip" => "scoala",
        "nume" => $scoala[13],
    ]);
}
$db->commit();
$db->exec("UPDATE scoli SET tip = 'liceu' WHERE nume LIKE 'liceul%' OR nume LIKE 'colegiul%'");

echo "Procesat CSV-ul cu scolile (" . round((microtime(true) - $start) * 1000) . " ms)\n";
fclose($file);


$insert = $db->prepare("INSERT INTO note (cod, scoala, judet, romana, matematica, materna, medie, init_romana, init_matematica, init_materna) values (:cod, :scoala, :judet, :romana, :matematica, :materna, :medie, :init_romana, :init_matematica, :init_materna)");
foreach($jud as $j){
    $start = microtime(true);
    $prezenta = [];
    $db->beginTransaction();
    $note = json_decode(file_get_contents($anul . "/" . $j . "-candidate.json"), false);
    foreach($note as $nota) {
        $nt = [
            "cod" => $nota->name,
            "judet" => $nota->county,
            "scoala" => $nota->schoolCode,
            "medie" => $nota->mev,
        ];
        if($nota->rf) {
            $nt["romana"] = $nota->rf;
            $nt["init_romana"] = $nota->ri;
        } else {
            $nt["romana"] = $nota->ri;
            $nt["init_romana"] = null;
        }
        if($nota->mf) {
            $nt["matematica"] = $nota->mf;
            $nt["init_matematica"] = $nota->mi;
        } else {
            $nt["matematica"] = $nota->mi;
            $nt["init_matematica"] = null;
        }
        if($nota->lmf) {
            $nt["materna"] = $nota->lmf;
            $nt["init_materna"] = $nota->lmi;
        } else {
            $nt["materna"] = $nota->lmi;
            $nt["init_materna"] = null;
        }

        if($nota->ra != $nota->rf || $nota->ma != $nota->mf || $nota->lma != $nota->lmf) {
            print_r($nota);
        }

        $insert->execute($nt);

        if(!isset($prezenta[$nota->schoolCode])) {
            $prezenta[$nota->schoolCode] = ["prezenti" => 0, "absenti" => 0];
        }
        if($nota->mev > 0) {
            $prezenta[$nota->schoolCode]["prezenti"]++;
        } else {
            $prezenta[$nota->schoolCode]["absenti"]++;
        }
    }
    $db->commit();

    $mid = microtime(true);

    $db->beginTransaction();
    foreach($prezenta as $cod => $prez) {
        $db->exec("UPDATE scoli set prezenti = " . $prez["prezenti"] . ", absenti = " . $prez["absenti"] . " WHERE cod = " . $cod);
    }
    $db->commit();

    echo "Procesat notele din " . strtoupper($j) . " (" . round(1000 * ($mid - $start)) . " ms insert, " . round(1000 * (microtime(true) - $mid)) . " ms prezenta)\n";
}


// niste curatenie, ca n-am gasit lista scolilor din 2024
if($anul == 2024){
    $db->exec("UPDATE scoli set cod = 0362103361 WHERE cod = 0361103361");
    $db->exec("UPDATE scoli set cod = 0962102093 WHERE cod = 0961102093");
    $db->exec("UPDATE scoli set cod = 1362101818 WHERE cod = 1361101818");
    $db->exec("UPDATE scoli set cod = 2262102587 WHERE cod = 2261102587");
    $db->exec("UPDATE scoli set cod = 2262104811 WHERE cod = 2261104811");
    $db->exec("UPDATE scoli set cod = 2262104856 WHERE cod = 2261104856");
    $db->exec("UPDATE scoli set cod = 2262105613 WHERE cod = 2261105613");
    $db->exec("UPDATE scoli set cod = 2462101217 WHERE cod = 2461101217");
    $db->exec("UPDATE scoli set cod = 2961102563 WHERE cod = 2962102563");
    $db->exec("UPDATE scoli set cod = 2962102997 WHERE cod = 2961102997");
    $db->exec("UPDATE scoli set cod = 2962100068 WHERE cod = 2961100068");
    $db->exec("UPDATE scoli set cod = 3462100055 WHERE cod = 3461100055");
    $db->exec("UPDATE scoli set cod = 3462101314 WHERE cod = 3461101314");
    $db->exec("UPDATE scoli set cod = 3861200314 WHERE cod = 3862200314");
    $db->exec("UPDATE scoli set cod = 3861102856 WHERE cod = 3862102856");
    $db->exec("INSERT INTO scoli (cod, judet, localitate, sat, mediu, tip, nume) VALUES (4061210198, 'B', 'SECTORUL 2', 'SECTORUL 2', 'URBAN', 'scoala', 'Şcoala Gimnazială „Evrika”')");
}

$db->exec("DELETE FROM scoli WHERE cod IN (SELECT s.cod FROM scoli AS s LEFT JOIN note AS n ON s.cod = n.scoala WHERE n.cod IS NULL)");
echo "Sters scolile orfane (fara nicio nota)\n";

