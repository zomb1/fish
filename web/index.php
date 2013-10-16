<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app['debug'] = true;


use Silex\Provider\FormServiceProvider;
use Symfony\Component\HttpFoundation\Request;

//Providers

$app->register(new FormServiceProvider());

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));
// no translate form
$app->register(new Silex\Provider\TranslationServiceProvider(), array(
    'translator.messages' => array(),
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
    'db.options' => array(
        'driver'   => 'pdo_sqlite',
        'path'     => __DIR__.'/../db/app.db',
    ),
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());

//------------Controlers----------------------

// home page
$app->get('/', function () use ($app) {
    return $app['twig']->render('index.twig');
})
->bind('homepage');

// view dictionary
$app->get('/show', function () use ($app) {
    $wordlist = $app['db']->fetchAll('SELECT * FROM Fiszki');
    return $app['twig']->render('show.twig', array('wordlist' => $wordlist));
})
->bind('showAllWords');

// load from CSV
$app->match('/loadcsv', function (Request $request) use ($app) {    
    $form = $app['form.factory']->createBuilder('form')
        ->add('attachment', 'file', array('label'  => 'Plik CSV',))
        ->getForm();

    if ('POST' == $request->getMethod()) {
        $form->bind($request);

        if ($form->isValid()) {
            //$someNewFilename = rand(1, 99999).'.'.'txt';
            //$dir= '/home/dorian/public_html/fiszki/db';
            //$file=$form['attachment']->getData();//->move($dir, $someNewFilename);
            
            $files = $request->files->get($form->getName());
            /* Make sure that Upload Directory is properly configured and writable */
            $path = __DIR__.'/../upload/';
            //$filename = $files['attachment']->getClientOriginalName();
            
            return $form->getName();
        }
    }

    return $app['twig']->render('loadCSV.twig', array('form' => $form->createView()));
})
->bind('loadCSV');

// delete word
$app->get('/delete/{id}', function ($id) use ($app) {
    $wordlist = $app['db']->delete('Fiszki', array('id' => $id));
    return 'ok';
})
->assert('id', '\d+')
->bind('deleteWord');

// show random word
$app->get('/random', function() use ($app) {
    $countWords = $app['db']->fetchAssoc('SELECT count(*) as count FROM Fiszki');
    $countWords =  $countWords['count'];
    if ($countWords>0){
        $random= mt_rand(1, $countWords);
        $sql = "SELECT * FROM Fiszki limit ".($random-1).",".($random);
        $word = $app['db']->fetchAssoc($sql);
        return $word['id'].' : '.$word['polishWord'].' : '.$word['foreignWord'];
    }else {
        return 'słownik jest pusty';
    }
})
->assert('id', '\d+')
->bind('randomWord');

// edit word
$app->match ('/edit/{id}', function (Request $request,$id) use ($app) {
    
    $sql = "SELECT * FROM Fiszki WHERE id = ?";
    $word = $app['db']->fetchAssoc($sql, array((int) $id));
    
    $form = $app['form.factory']->createBuilder('form',$word)
        ->add('polishWord', 'text', array('label'  => 'Wyraz w języku polskim',))
        ->add('foreignWord', 'text', array('label'  => 'Wyraz w języku obcym',))
        ->getForm();

    if ('POST' == $request->getMethod()) {
        $form->bind($request);

        if ($form->isValid()) {
            $data = $form->getData();

            $sql = "UPDATE Fiszki SET polishWord = ?, foreignWord = ? WHERE id = ?";
            $app['db']->executeUpdate($sql, array($data['polishWord'],$data['foreignWord'], (int) $id));
            
            return $app->redirect('../show');
        }
    }

    return $app['twig']->render('edit.twig', array('form' => $form->createView()));
})
->assert('id', '\d+')
->bind('editWord');

//add to words dictionary
$app->match('/dodaj/{number}', function (Request $request, $number) use ($app) {

    $form = $app['form.factory']->createBuilder('form')
        ->add('polishWord', 'text', array('label'  => 'Wyraz w języku polskim',))
        ->add('foreignWord', 'text', array('label'  => 'Wyraz w języku obcym',))
        ->getForm();

    if ('POST' == $request->getMethod()) {
        $form->bind($request);

        if ($form->isValid()) {
            $data = $form->getData();
            $app['db']->insert('Fiszki', array('polishWord' => $data['polishWord'],'foreignWord' => $data['foreignWord']));

            return $app->redirect('./'.$number);
        }
    }

    return $app['twig']->render('add.twig', array('form' => $form->createView()));
})
->assert('number', '\d+')
->bind('add');

$app->run();
