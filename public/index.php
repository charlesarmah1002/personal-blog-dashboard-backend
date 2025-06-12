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
use Cloudinary\Cloudinary;

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

    if (!empty($errors)) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'errorMessage' => $errors
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $image = $files['image'];

        if ($image->getError() === UPLOAD_ERR_OK) {

            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                    'api_key' => $_ENV['CLOUDINARY_API_KEY'],
                    'api_secret' => $_ENV['CLOUDINARY_API_SECRET']
                ]
            ]);
        } else {
            throw new Exception("File upload failed");
        }

        $upload_response = $cloudinary->uploadApi()->upload($image->getFilePath());
        $upload_url = $upload_response['secure_url'];

        $database = new \App\Database;
        $pdo = $database->getConnection();

        $stmt = $pdo->prepare("INSERT INTO blog_posts(title, post, image) VALUES(:title, :post, :image)");
        $stmt->execute([
            ':title' => $postDetails['title'],
            ':post' => $postDetails['post'],
            ':image' => $upload_url
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

        $response->getBody()->write(json_encode([
            "success" => true,
            "successMessage" => "Posts retrieved successfully",
            "data" => $posts
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

// add route for collecting one blog post
$app->get('/{id}', function (Request $request, Response $response, array $args) {
    $database = new \App\Database;
    $pdo = $database->getConnection();

    if (!preg_match('/^\d+$/', $args['id'])) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "errorMessage" => "Invalid post id"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(404);
    }

    try {
        $database = new \App\Database;
        $pdo = $database->getConnection();

        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE id = :id");
        $stmt->execute([
            ':id' => $args['id']
        ]);
        $blog_post = $stmt->fetch(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            "success" => true,
            "successMessage" => "Post retrieved successfully",
            "data" => $blog_post
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

// add route for updating blog post
$app->post('/{id}', function (Request $request, Response $response, array $args) {
    $errors = [];
    $postDetails = $request->getParsedBody();
    $files = $request->getUploadedFiles();

    $database = new \App\Database;
    $pdo = $database->getConnection();

    if (!preg_match('/^\d+$/', $args['id'])) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "errorMessage" => "Invalid post id"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(404);
    }

    if (empty($postDetails['title'])) {
        $errors['title'] = "Title is required";
    }

    if (empty($postDetails['post'])) {
        $errors['post'] = "Cannot publish empty post";
    }

    if (empty($files['image'])) {
        $errors['image'] = "Cannot publish without image";
    }

    if (!empty($errors)) {
        $response->getBody()->write(json_encode([
            'success' => false,
            'errorMessage' => $errors
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    try {
        $image = $files['image'];

        if ($image->getError() === UPLOAD_ERR_OK) {

            $cloudinary = new Cloudinary([
                'cloud' => [
                    'cloud_name' => $_ENV['CLOUDINARY_CLOUD_NAME'],
                    'api_key' => $_ENV['CLOUDINARY_API_KEY'],
                    'api_secret' => $_ENV['CLOUDINARY_API_SECRET']
                ]
            ]);
        } else {
            throw new Exception("File upload failed");
        }

        $upload_response = $cloudinary->uploadApi()->upload($image->getFilePath());
        $upload_url = $upload_response['secure_url'];

        $stmt = $pdo->prepare("UPDATE blog_posts SET title = :title, post = :post, image = :image WHERE id = :id");
        $stmt->execute([
            ':title' => $postDetails['title'],
            ':post' => $postDetails['post'],
            ':image' => $upload_url,
            ':id' => $args['id']
        ]);

        $response->getBody()->write(json_encode([
            "success" => true,
            "successMessage" => "Post updated successfully"
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

// add route for deleting post
$app->delete('/{id}', function (Request $request, Response $response, array $args) {
    $database = new \App\Database;
    $pdo = $database->getConnection();

    if (!preg_match('/^\d+$/', $args['id'])) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "errorMessage" => "Invalid post id"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(404);
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM blog_posts WHERE id = :id");
        $stmt->execute([
            ':id' => $args['id']
        ]);

        $response->getBody()->write(json_encode([
            "success" => true,
            "successMessage" => "Post deleted successfully"
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    } catch (\Throwable $th) {
        $response->getBody()->write(json_encode([
            "success" => false,
            "errorMessage" => $th->getMessage()
        ]));
        return $response->withHeader("Content-Type", "application/json")->withStatus(200);
    }
});

// add route for searching through post by title
$app->get('/search/{param}', function (Request $request, Response $response, array $args) {
    $database = new \App\Database;
    $pdo = $database->getConnection();

    $search_param = htmlspecialchars(trim($postDetails['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $parameter = '%' . $search_param . '%';


    try {
        $stmt = $pdo->prepare("SELECT * FROM blog_posts WHERE title LIKE :search_param");
        $stmt->execute([
            ":search_param" => $parameter
        ]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response->getBody()->write(json_encode([
            "success" => true,
            "successMessage" => "Posts retrieved successfully",
            "data" => $data
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

function moveUploadedFile($directory, \Psr\Http\Message\UploadedFileInterface $uploadedFile)
{
    $extension = pathinfo($uploadedFile->getClientFilename(), PATHINFO_EXTENSION);
    $basename = bin2hex(random_bytes(8)); // Random filename
    $filename = sprintf('%s.%0.8s', $basename, $extension);

    $uploadedFile->moveTo($directory . DIRECTORY_SEPARATOR . $filename);

    return $filename;
}

$app->run();