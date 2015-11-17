<?php

define('ES_URL', 'http://127.0.0.1:9200');
$tmp = explode('?', $_SERVER['REQUEST_URI']);
define('ROOT', array_shift($tmp));

function getResult($h) {
    $result = curl_exec($h);
    if( 200 !== curl_getinfo($h, CURLINFO_HTTP_CODE) ) {
        exit('Erreur lors de la création d\'index : '.PHP_EOL."<pre>".var_export(json_decode($result, true), true)."</pre>");
    } else {
        header('Location: '.ROOT);
    }
}

$h = curl_init(ES_URL);
//------------------------------------------------------------------------------
//Est-ce qu'ElasticSearch est en ligne ?
curl_setopt_array($h, [
    CURLOPT_NOBODY => true,
    CURLOPT_RETURNTRANSFER => true
]);
$result = curl_exec($h);

if( 200 !== curl_getinfo($h, CURLINFO_HTTP_CODE) ) {
    exit('ElasticSearch n\'est pas démarré !');
}

curl_reset($h);
//------------------------------------------------------------------------------
//Liste des index
curl_setopt_array($h, [
    CURLOPT_URL => ES_URL.'/_cat/indices?format=json',
    CURLOPT_RETURNTRANSFER => true
]);
$result = curl_exec($h);
curl_reset($h);
$indices = json_decode($result);
//------------------------------------------------------------------------------
//Création d'un index
if( isset($_GET['create']) ) {
    curl_setopt_array($h, [
        CURLOPT_URL => ES_URL.'/'.$_GET['name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true
    ]);
    getResult($h);
}
//------------------------------------------------------------------------------
//Suppression d'un index
if( isset($_GET['delete']) ) {
    curl_setopt_array($h, [
        CURLOPT_URL => ES_URL.'/'.$_GET['name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE'
    ]);
    getResult($h);
}
//------------------------------------------------------------------------------
//Création d'un inde
//------------------------------------------------------------------------------
curl_close($h);
?>
<html>
    <head>
        <title>ElasticSearch demo</title>
        <style type="text/css">
            body { font-family: sans-serif; }
            a { text-decoration: none; }
            a.delete { text-decoration: underline; color: red;}
            td, th { border: 1px solid black; padding: 10px; }
        </style>
    </head>
    <body>
        <h3>Création d'index</h3>
        <form>
            <label>Nom de l'index:</label>
            <input type="text" name="name" />
            <input type="submit" name="create" value="Créer" />
        </form>
        <hr/>
        <?php if( count($indices) > 0 ): ?>
        <h3>Liste des index :</h3>
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Statut</th>
                    <th>Nombre de shards</th>
                    <th>Nombre de replicas</th>
                    <th>Nombre de documents</th>
                    <th>Nombre de Suppression</th>
                    <th>Taille des données</th>
                    <th>Taille des données primaires</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach( $indices as $index ): ?>
            <tr>
                <td style="background-color: <?= $index->health ?>"><a target="_blank" href="<?= ES_URL.'/'.$index->index.'/_search?pretty' ?>"><?= $index->index ?></a></td>
                <td><?= $index->status ?></td>
                <td><?= $index->pri ?></td>
                <td><?= $index->rep ?></td>
                <td><?= $index->{'docs.count'} ?></td>
                <td><?= $index->{'docs.deleted'} ?></td>
                <td><?= $index->{'store.size'} ?></td>
                <td><?= $index->{'pri.store.size'} ?></td>
                <td><a class="delete" onclick="if( confirm('Êtes vous sur de vouloir supprimer l\'index?') ) {window.location='<?= ROOT.'?delete&name='.$index->index ?>'; }">[X]</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <hr/>
        <h3>Indexer des données</h3>
        <form>
            <p>
                <label>Nom de l'index:</label>
                <select name="name">
                    <?php foreach( $indices as $index ): ?>
                    <option value="<?= $index->index ?>"><?= $index->index ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <p>
                <label>Nombre de documents: </label>
                <input type="number" />
            </p>
            <p>
                <label>Document: </label>
                <select name="name">
                    <?php foreach( glob(__DIR__.'/data/*.json') as $file ): ?>
                    <option value="<?= basename($file) ?>"><?= basename($file) ?></option>
                    <?php endforeach; ?>
                </select>
            </p>
            <input type="submit" name="create" value="Créer" />
        </form>
        <?php endif; ?>
    </body>
</html>
