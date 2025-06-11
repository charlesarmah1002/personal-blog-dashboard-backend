<?php

declare(strict_types=1);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Dotenv\Dotenv;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$app->post('/', function (Request $request, Response $response) {
    $postDetails = $request->getParsedBody();
    $files = $request->getUploadedFiles();

    $errors = [];

    if (empty($postDetails['title'])) {
        $errors['title'] = "Title is required";
    }

    if (empty($postDetails['post'])) {
        $errors['post'] = "Cannot publish empty post";
    }

    if (empty($files['image'])) {
        $errors['image'] = "Cannot publish without image";
    }

    if (!empty ($errors)) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'errorMessage' => $errors
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $image = $files['image'];

        if ($image->getError() === UPLOAD_ERR_OK) {
            $filename = moveUploadedFile(__DIR__ . '/../uploads', $image);
        }else {
            throw new Exception("File upload failed");
        }

        $database = new \App\Database;
        $pdo = $database->getConnection();

        $stmt = $pdo->prepare("INSERT INTO blog_posts(title, post, image) VALUES(:title, :post, :image)");
        $stmt->execute([
            ':title' => $postDetails['title'],
            ':post' => $postDetails['post'],
            ':image' => $filename
        ]);

        $response->getBody()->write(json_encode([
            'success' => true,
            'successMessage' => 'Post published successfully'
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    } catch (\Throwable $th) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "errorMessage" => $th->getMessage()
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(400);
    }
});

$app->get('/', function (Request $request, Response $response) {
    $database = new \App\Database;
    $pdo = $database->getConnection();

    try {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts");
        $stmt->execute();
        $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($posts as &$post) {
            $post['image'] = __DIR__ . '/../uploads/' . $post['image'];
        }
        unset($post);

        $response->getBody()->write(json_encode([
            "success" => true,
            "successMessage" => "Posts retrieved successfully",
            "data" => $posts
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }catch (\Throwable $th) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "errorMessage" => $th->getMessage()
        ]));
        return $response->withHeader("Content-Type" ,"application/json")->withStatus(400);
    }
});

function moveUploadedFile($directory, \Psr\Http\Message\UploadedFileInterface $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // Random filename
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

$app->run();