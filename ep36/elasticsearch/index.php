<?php

define('ES_URL', 'http://127.0.0.1:9200');
$tmp = explode('?', $_SERVER['REQUEST_URI']);
define('ROOT', array_shift($tmp));
//##############################################################################
function getResult($h, $redirect = true) {
    $result = curl_exec($h);
    if( "2" !== substr(curl_getinfo($h, CURLINFO_HTTP_CODE), 0, 1) ) {
        var_dump($result);
        exit('Erreur lors l\'operation : '.PHP_EOL."<pre>".var_export(json_decode($result, true), true)."</pre>");
    } elseif( $redirect ) {
        header('Location: '.ROOT);
    }

    return $result;
}
//##############################################################################
$h = curl_init(ES_URL);
//##############################################################################
//Est-ce qu'ElasticSearch est en ligne ?
curl_setopt_array($h, [
    CURLOPT_RETURNTRANSFER => true
]);
$nodeDetail = curl_exec($h);

if( 200 !== curl_getinfo($h, CURLINFO_HTTP_CODE) ) {
    exit('ElasticSearch n\'est pas démarré !');
}

curl_reset($h);
//##############################################################################
//Liste des index
curl_setopt_array($h, [
    CURLOPT_URL => ES_URL.'/_cat/indices?format=json',
    CURLOPT_RETURNTRANSFER => true
]);
$result = curl_exec($h);
curl_reset($h);
$indices = json_decode($result);
//##############################################################################
//Création d'un index
if( isset($_POST['create']) ) {
    curl_setopt_array($h, [
        CURLOPT_URL => ES_URL.'/'.$_POST['name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true
    ]);
    getResult($h);
}
//##############################################################################
//Suppression d'un index
if( isset($_GET['delete']) ) {
    curl_setopt_array($h, [
        CURLOPT_URL => ES_URL.'/'.$_GET['name'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE'
    ]);
    getResult($h);
}
//##############################################################################
//Remplissage d'un index
if( isset($_POST['index']) ) {
    ini_set('execution_time', -1);
    echo "Indexation en cours...";
    $type = basename($_POST['data'], '.json');
    $url = ES_URL.'/'.$_POST['name'].'/'.$type;
    $nb = $_POST['count'];

    curl_setopt_array($h, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true
    ]);
    while( $nb > 0 ) {
        ob_start();
        require __DIR__.'/data/'.$_POST['data'];
        $buffer = ob_get_clean();

        curl_setopt($h, CURLOPT_POSTFIELDS, $buffer);
        getResult($h, false);
        $nb--;
    }
    header('Location: '.ROOT);
}
//##############################################################################
//Recherche dans un index
if( isset($_POST['search']) ) {
    $url = ES_URL.'/'.$_POST['name'].'/_search?pretty';

    ob_start();
    require __DIR__.'/query/'.$_POST['query'];
    $buffer = ob_get_clean();
    curl_setopt_array($h, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $buffer,
        CURLOPT_POST => true
    ]);
    $queryResult = getResult($h, false);
}
//##############################################################################
//Enregistrement mapping
if( isset($_POST['mapping']) ) {
    $url = ES_URL.'/'.$_POST['name'].'/_mapping/'.basename($_POST['data'], '.json');

    ob_start();
    require __DIR__.'/mapping/'.$_POST['data'];
    $buffer = ob_get_clean();
    var_dump($buffer);
    curl_setopt_array($h, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $buffer
    ]);
    curl_setopt($h, CURLOPT_CUSTOMREQUEST, 'PUT');
    $mappingResult = getResult($h, false);
}
//##############################################################################
curl_close($h);
?>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>ElasticSearch demo</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" />
        <link rel="stylesheet" href="assets/prism.css" />
        <script type="text/javascript" src="assets/prism.js"></script>
    </head>
    <body>
        <div class="jumbotron">
            <div class="container">
                <h1>ElasticSearch <small><a href="<?= ES_URL ?>"><?= ES_URL ?></a></small></h1>
                <pre><code class="language-json"><?= $nodeDetail ?></code></pre>
            </div>
        </div>
        <div class="container">
            <?php if( count($indices) > 0 ): ?>
            <div class="row">
                <div class="col-md-12">
                    <h2>Liste des index</h2>
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Nom</th>
                                <th>Statut</th>
                                <th>Shards</th>
                                <th>Replicas</th>
                                <th>Documents</th>
                                <th>Suppression</th>
                                <th>Données</th>
                                <th>Données primaires</th>
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
                            <td>
                                <a class="btn btn-danger btn-sm" data-toggle="tooltip" title="Supprimer l'index" onclick="if( confirm('Êtes vous sur de vouloir supprimer l\'index?') ) {window.location='<?= ROOT.'?delete&name='.$index->index ?>';}">
                                    <i class="glyphicon glyphicon-remove"></i>
                                </a>
                                <a target="_blank" class="btn btn-warning btn-sm" data-toggle="tooltip" title="Voir le mapping" href="<?= ES_URL.'/'.$index->index.'/_mapping?pretty' ?>">
                                    <i class="glyphicon glyphicon-info-sign"></i>
                                </a>
                                <a target="_blank" class="btn btn-success btn-sm" data-toggle="tooltip" title="Voir les résultats" href="<?= ES_URL.'/'.$index->index.'/_search?pretty' ?>">
                                    <i class="glyphicon glyphicon-search"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingOne">
                    <h2 class="panel-title">Indexer des données</h2>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-inline">
                        <div class="form-group">
                            <label>Nom:</label>
                            <select class="form-control" name="name">
                                <?php foreach( $indices as $index ): ?>
                                <option value="<?= $index->index ?>"><?= $index->index ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Document: </label>
                            <select class="form-control" name="data">
                                <?php foreach( glob(__DIR__.'/data/*.json') as $file ): ?>
                                <option value="<?= basename($file) ?>"><?= basename($file) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nombre: </label>
                            <input class="form-control" type="number" value="100" name="count" />
                        </div>
                        <input type="submit" class="btn btn-warning" name="index" value="Indexer" />
                    </form>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingOne">
                    <h2 class="panel-title">Chercher des données</h2>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-inline">
                        <div class="form-group">
                            <label>Nom:</label>
                            <select class="form-control" name="name">
                                <?php foreach( $indices as $index ): ?>
                                <option value="<?= $index->index ?>"><?= $index->index ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Requête: </label>
                            <select class="form-control" name="query">
                                <?php foreach( glob(__DIR__.'/query/*.json') as $file ): ?>
                                <option value="<?= basename($file) ?>"><?= basename($file) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="submit" class="btn btn-warning" name="search" value="Chercher" />
                    </form>
                    <?php if( isset($queryResult) ): ?>
                        <h3>Résultat de la requête <code><?= $_POST['query']; ?></code></h3>
                        <pre><code class="language-json"><?= file_get_contents(__DIR__.'/query/'.$_POST['query']); ?></code></pre>
                        <pre><code class="language-json"><?= $queryResult; ?></code></pre>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingOne">
                    <h2 class="panel-title">Initialiser le mapping</h2>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-inline">
                        <div class="form-group">
                            <label>Nom:</label>
                            <select class="form-control" name="name">
                                <?php foreach( $indices as $index ): ?>
                                <option value="<?= $index->index ?>"><?= $index->index ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Mapping: </label>
                            <select class="form-control" name="data">
                                <?php foreach( glob(__DIR__.'/mapping/*.json') as $file ): ?>
                                <option value="<?= basename($file) ?>"><?= basename($file) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="submit" class="btn btn-warning" name="mapping" value="Enregistrer" />
                    </form>
                    <?php if( isset($mappingResult) ): ?>
                        <pre><code class="language-json"><?= $mappingResult; ?></code></pre>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="panel panel-default">
                <div class="panel-heading" role="tab" id="headingOne">
                    <h2 class="panel-title">Création d'index</h2>
                </div>
                <div class="panel-body">
                    <form method="post" class="form-inline">
                        <div class="form-group">
                            <input type="text" class="form-control" name="name" placeholder="Nom" />
                        </div>
                        <input type="submit" class="btn btn-success" name="create" value="Créer" />
                    </form>
                </div>
            </div>
        </div>
        <script type="text/javascript" src="https://code.jquery.com/jquery-2.1.4.min.js"></script>
        <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>
        <script type="text/javascript">
            $(function () {
                $('[data-toggle="tooltip"]').tooltip()
            })
        </script>
    </body>
</html>
