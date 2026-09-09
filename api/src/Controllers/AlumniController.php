<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Application\Settings\SettingsInterface;
use App\Database;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AlumniController
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * GET /api/v1/alumni/count
     * Endpoint #3: Return total number of alumni
     */
    public function count(Request $request, Response $response): Response
    {
        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));
        $stmt = $db->query('SELECT COUNT(*) as total FROM alumni');
        $result = $stmt->fetch();

        // Return just an integer as specified
        $response->getBody()->write(json_encode((int) $result['total']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /api/v1/alumni/
     * Endpoint #5: Get all alumni
     */
    public function getAll(Request $request, Response $response): Response
    {
        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        $stmt = $db->query(
            'SELECT id, first_name, last_name, email, enrollment_year, graduation_year, created_at
             FROM alumni ORDER BY last_name ASC'
        );
        $alumni = $stmt->fetchAll();

        // Attach jobs to each alumnus
        $jobStmt = $db->prepare(
            'SELECT company_name, job_title, country, city, latitude, longitude, is_current, start_date
             FROM jobs WHERE alumnus_id = :alumnus_id ORDER BY start_date DESC'
        );

        foreach ($alumni as &$alumnus) {
            $jobStmt->execute([':alumnus_id' => $alumnus['id']]);
            $alumnus['jobs'] = $jobStmt->fetchAll();
        }
        unset($alumnus);

        return $this->jsonResponse($response, [
            'status' => 'success',
            'count' => count($alumni),
            'data' => $alumni,
        ]);
    }

    /**
     * GET /api/v1/alumni/search
     * Endpoint #8: Search alumni with multiple criteria + pagination (per 4) + JSON/XML support
     */
    public function search(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();

        $name = $params['name'] ?? '';
        $enrollmentYear = $params['enrollment_year'] ?? '';
        $graduationYear = $params['graduation_year'] ?? '';
        $country = $params['country'] ?? '';
        $page = max(1, (int) ($params['page'] ?? 1));
        $format = strtolower($params['format'] ?? 'json');
        $perPage = 4;

        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        // Build WHERE clause dynamically
        $conditions = [];
        $bindings = [];

        if (!empty($name)) {
            $conditions[] = '(a.first_name LIKE :name_first OR a.last_name LIKE :name_last)';
            $bindings[':name_first'] = '%' . $name . '%';
            $bindings[':name_last'] = '%' . $name . '%';
        }
        if (!empty($enrollmentYear)) {
            $conditions[] = 'a.enrollment_year = :enrollment_year';
            $bindings[':enrollment_year'] = (int) $enrollmentYear;
        }
        if (!empty($graduationYear)) {
            $conditions[] = 'a.graduation_year = :graduation_year';
            $bindings[':graduation_year'] = (int) $graduationYear;
        }
        if (!empty($country)) {
            $conditions[] = 'j.country LIKE :country';
            $bindings[':country'] = '%' . $country . '%';
        }

        $whereClause = '';
        if (!empty($conditions)) {
            $whereClause = 'WHERE ' . implode(' AND ', $conditions);
        }

        // Count total matching records
        $countSql = "SELECT COUNT(DISTINCT a.id) as total 
                     FROM alumni a 
                     LEFT JOIN jobs j ON a.id = j.alumnus_id 
                     $whereClause";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($bindings);
        $totalRecords = (int) $countStmt->fetch()['total'];
        $totalPages = max(1, (int) ceil($totalRecords / $perPage));

        // Ensure page doesn't exceed total pages
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $offset = ($page - 1) * $perPage;

        // Fetch alumni with their current job for country search
        $dataSql = "SELECT DISTINCT a.id, a.first_name, a.last_name, a.email,
                    a.enrollment_year, a.graduation_year, a.created_at
                    FROM alumni a
                    LEFT JOIN jobs j ON a.id = j.alumnus_id
                    $whereClause
                    ORDER BY a.last_name ASC
                    LIMIT :limit OFFSET :offset";

        $dataStmt = $db->prepare($dataSql);
        foreach ($bindings as $key => $val) {
            $dataStmt->bindValue($key, $val);
        }
        $dataStmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $dataStmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $dataStmt->execute();
        $alumni = $dataStmt->fetchAll();

        // For each alumnus, get their current job (for country info)
        $jobStmt = $db->prepare(
            'SELECT company_name, job_title, country, city, latitude, longitude, is_current, start_date
             FROM jobs WHERE alumnus_id = :alumnus_id ORDER BY start_date DESC'
        );

        $resultData = [];
        foreach ($alumni as $alumnus) {
            $jobStmt->execute([':alumnus_id' => $alumnus['id']]);
            $jobs = $jobStmt->fetchAll();
            $alumnus['jobs'] = $jobs;
            $resultData[] = $alumnus;
        }

        $responseData = [
            'status' => 'success',
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
            ],
            'data' => $resultData,
        ];

        if ($format === 'xml') {
            return $this->xmlResponse($response, $responseData);
        }

        return $this->jsonResponse($response, $responseData);
    }

    /**
     * GET /api/v1/alumni/{id}/jobs/
     * Endpoint #4: Get employment details of a specific alumnus
     */
    public function getJobs(Request $request, Response $response, array $args): Response
    {
        $alumnusId = (int) ($args['id'] ?? 0);
        if ($alumnusId <= 0) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Invalid alumnus ID',
            ], 400);
        }

        $settings = $this->container->get(SettingsInterface::class);
        $db = Database::getConnection($settings->get('db'));

        // Check if alumnus exists
        $stmt = $db->prepare('SELECT id, first_name, last_name FROM alumni WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $alumnusId]);
        $alumnus = $stmt->fetch();

        if (!$alumnus) {
            return $this->jsonResponse($response, [
                'status' => 'error',
                'message' => 'Alumnus not found',
            ], 404);
        }

        // Get all jobs for this alumnus
        $stmt = $db->prepare('SELECT * FROM jobs WHERE alumnus_id = :alumnus_id ORDER BY start_date DESC');
        $stmt->execute([':alumnus_id' => $alumnusId]);
        $jobs = $stmt->fetchAll();

        return $this->jsonResponse($response, [
            'status' => 'success',
            'alumnus' => $alumnus,
            'count' => count($jobs),
            'data' => $jobs,
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }

    private function xmlResponse(Response $response, array $data): Response
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response/>');
        $this->arrayToXml($data, $xml);

        $response->getBody()->write($xml->asXML());
        return $response
            ->withHeader('Content-Type', 'application/xml')
            ->withStatus(200);
    }

    private function arrayToXml(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            // XML tags cannot start with a number
            $tagName = is_numeric($key) ? 'item' : (string) $key;

            if (is_array($value)) {
                // Check if it's a sequential array
                if (array_keys($value) === range(0, count($value) - 1)) {
                    $child = $xml->addChild($tagName);
                    $this->arrayToXml($value, $child);
                } else {
                    $child = $xml->addChild($tagName);
                    $this->arrayToXml($value, $child);
                }
            } else {
                $xml->addChild($tagName, htmlspecialchars((string) $value));
            }
        }
    }
}
