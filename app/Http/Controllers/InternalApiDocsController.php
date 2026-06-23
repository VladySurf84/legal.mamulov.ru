<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class InternalApiDocsController extends Controller
{
    public function index(): View
    {
        return view('internal-api-docs.index');
    }

    public function spec(): JsonResponse
    {
        return response()->json([
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'Legal Internal API',
                'version' => '1.0.0',
                'description' => 'Внутреннее API legal.mamulov.ru для синхронизации электронных подписей и удаленной подписи данных через CryptoPro на сервере.',
            ],
            'servers' => [
                [
                    'url' => url('/'),
                    'description' => 'Текущий сервер',
                ],
                [
                    'url' => 'https://legal.mamulov.ru',
                    'description' => 'Production',
                ],
            ],
            'security' => [
                ['bearerAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'internal-api-token',
                        'description' => 'Для запросов нужен Bearer token из SIGNATURE_SYNC_API_TOKEN. Передается как Authorization: Bearer <token>.',
                    ],
                ],
                'schemas' => [
                    'SignatureOwner' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'nullable' => true, 'example' => 'legal'],
                            'id' => ['type' => 'string', 'nullable' => true, 'example' => '771548701079'],
                            'legal_name' => ['type' => 'string', 'nullable' => true, 'example' => 'ИП Рыбникова'],
                            'legal_inn' => ['type' => 'string', 'nullable' => true, 'example' => '771548701079'],
                        ],
                    ],
                    'Signature' => [
                        'type' => 'object',
                        'properties' => [
                            'signature_id' => ['type' => 'integer', 'example' => 13],
                            'provider' => ['type' => 'string', 'example' => 'cryptopro'],
                            'credential_type' => ['type' => 'string', 'example' => 'certificate_thumbprint'],
                            'name' => ['type' => 'string', 'nullable' => true, 'example' => 'Рыбникова Анна'],
                            'status' => ['type' => 'string', 'example' => 'active'],
                            'owner' => ['$ref' => '#/components/schemas/SignatureOwner'],
                            'thumbprint' => ['type' => 'string', 'nullable' => true],
                            'thumbprint_tail' => ['type' => 'string', 'nullable' => true],
                            'subject' => ['type' => 'string', 'nullable' => true],
                            'subject_type' => ['type' => 'string', 'nullable' => true, 'example' => 'individual_entrepreneur'],
                            'subject_type_label' => ['type' => 'string', 'nullable' => true, 'example' => 'ИП'],
                            'issuer' => ['type' => 'string', 'nullable' => true],
                            'serial' => ['type' => 'string', 'nullable' => true],
                            'legal_inn' => ['type' => 'string', 'nullable' => true],
                            'ogrnip' => ['type' => 'string', 'nullable' => true],
                            'ogrn' => ['type' => 'string', 'nullable' => true],
                            'snils' => ['type' => 'string', 'nullable' => true],
                            'valid_from' => ['type' => 'string', 'nullable' => true],
                            'valid_to' => ['type' => 'string', 'nullable' => true],
                            'container' => ['type' => 'string', 'nullable' => true],
                            'last_used_at' => ['type' => 'string', 'nullable' => true],
                            'created_at' => ['type' => 'string', 'nullable' => true],
                            'updated_at' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'SignatureListResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Signature'],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'count' => ['type' => 'integer'],
                                    'server_time' => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ],
                        ],
                    ],
                    'SignatureImportResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Signature'],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'import' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'found' => ['type' => 'integer'],
                                            'imported' => ['type' => 'integer'],
                                            'updated' => ['type' => 'integer'],
                                            'skipped' => ['type' => 'integer'],
                                        ],
                                    ],
                                    'count' => ['type' => 'integer'],
                                    'server_time' => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ],
                        ],
                    ],
                    'SignRequest' => [
                        'type' => 'object',
                        'required' => ['data'],
                        'properties' => [
                            'data' => ['type' => 'string', 'description' => 'Строка для подписи или base64-представление bytes.'],
                            'data_encoding' => ['type' => 'string', 'enum' => ['utf8', 'base64'], 'default' => 'utf8'],
                            'detached' => ['type' => 'boolean', 'default' => false],
                        ],
                    ],
                    'SignResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'object',
                                'properties' => [
                                    'signature_id' => ['type' => 'integer'],
                                    'signature' => ['type' => 'string'],
                                    'signature_encoding' => ['type' => 'string', 'example' => 'base64'],
                                    'detached' => ['type' => 'boolean'],
                                    'signed_at' => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ],
                        ],
                    ],
                    'ErrorResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/api/internal/signatures' => [
                    'get' => [
                        'summary' => 'Список электронных подписей',
                        'description' => 'Возвращает безопасные карточки подписей. Закрытые ключи и encrypted_secret наружу не отдаются.',
                        'tags' => ['Signatures'],
                        'parameters' => [
                            ['name' => 'legal_id', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'legal_inn', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'status', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'subject_type', 'in' => 'query', 'schema' => ['type' => 'string']],
                            ['name' => 'changed_since', 'in' => 'query', 'schema' => ['type' => 'string', 'format' => 'date-time']],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Список подписей',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SignatureListResponse'],
                                    ],
                                ],
                            ],
                            '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        ],
                    ],
                ],
                '/api/internal/signatures/import' => [
                    'post' => [
                        'summary' => 'Импортировать подписи из CryptoPro',
                        'description' => 'Запускает импорт сертификатов из CryptoPro на сервере и возвращает актуальный список подписей.',
                        'tags' => ['Signatures'],
                        'responses' => [
                            '200' => [
                                'description' => 'Импорт выполнен',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SignatureImportResponse'],
                                    ],
                                ],
                            ],
                            '401' => ['$ref' => '#/components/responses/Unauthorized'],
                        ],
                    ],
                ],
                '/api/internal/signatures/{signature}' => [
                    'get' => [
                        'summary' => 'Карточка подписи',
                        'tags' => ['Signatures'],
                        'parameters' => [
                            [
                                'name' => 'signature',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Карточка подписи',
                                'content' => [
                                    'application/json' => [
                                        'schema' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'data' => ['$ref' => '#/components/schemas/Signature'],
                                                'meta' => ['type' => 'object'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                            '401' => ['$ref' => '#/components/responses/Unauthorized'],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                        ],
                    ],
                ],
                '/api/internal/signatures/{signature}/sign' => [
                    'post' => [
                        'summary' => 'Подписать данные через CryptoPro',
                        'description' => 'Сервер подписывает переданные данные выбранной подписью. Закрытый ключ остается на сервере.',
                        'tags' => ['Signatures'],
                        'parameters' => [
                            [
                                'name' => 'signature',
                                'in' => 'path',
                                'required' => true,
                                'schema' => ['type' => 'integer'],
                            ],
                        ],
                        'requestBody' => [
                            'required' => true,
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/SignRequest'],
                                ],
                                'application/x-www-form-urlencoded' => [
                                    'schema' => ['$ref' => '#/components/schemas/SignRequest'],
                                ],
                            ],
                        ],
                        'responses' => [
                            '200' => [
                                'description' => 'Данные подписаны',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/SignResponse'],
                                    ],
                                ],
                            ],
                            '401' => ['$ref' => '#/components/responses/Unauthorized'],
                            '404' => ['$ref' => '#/components/responses/NotFound'],
                            '422' => [
                                'description' => 'Ошибка подписи или валидации',
                                'content' => [
                                    'application/json' => [
                                        'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'components' => [
                'securitySchemes' => [
                    'bearerAuth' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'bearerFormat' => 'internal-api-token',
                        'description' => 'Для запросов нужен Bearer token из SIGNATURE_SYNC_API_TOKEN. Передается как Authorization: Bearer <token>.',
                    ],
                ],
                'responses' => [
                    'Unauthorized' => [
                        'description' => 'Неверный internal API token',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            ],
                        ],
                    ],
                    'NotFound' => [
                        'description' => 'Запись не найдена',
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => '#/components/schemas/ErrorResponse'],
                            ],
                        ],
                    ],
                ],
                'schemas' => [
                    'SignatureOwner' => [
                        'type' => 'object',
                        'properties' => [
                            'type' => ['type' => 'string', 'nullable' => true, 'example' => 'legal'],
                            'id' => ['type' => 'string', 'nullable' => true, 'example' => '771548701079'],
                            'legal_name' => ['type' => 'string', 'nullable' => true, 'example' => 'ИП Рыбникова'],
                            'legal_inn' => ['type' => 'string', 'nullable' => true, 'example' => '771548701079'],
                        ],
                    ],
                    'Signature' => [
                        'type' => 'object',
                        'properties' => [
                            'signature_id' => ['type' => 'integer', 'example' => 13],
                            'provider' => ['type' => 'string', 'example' => 'cryptopro'],
                            'credential_type' => ['type' => 'string', 'example' => 'certificate_thumbprint'],
                            'name' => ['type' => 'string', 'nullable' => true, 'example' => 'Рыбникова Анна'],
                            'status' => ['type' => 'string', 'example' => 'active'],
                            'owner' => ['$ref' => '#/components/schemas/SignatureOwner'],
                            'thumbprint' => ['type' => 'string', 'nullable' => true],
                            'thumbprint_tail' => ['type' => 'string', 'nullable' => true],
                            'subject' => ['type' => 'string', 'nullable' => true],
                            'subject_type' => ['type' => 'string', 'nullable' => true, 'example' => 'individual_entrepreneur'],
                            'subject_type_label' => ['type' => 'string', 'nullable' => true, 'example' => 'ИП'],
                            'issuer' => ['type' => 'string', 'nullable' => true],
                            'serial' => ['type' => 'string', 'nullable' => true],
                            'legal_inn' => ['type' => 'string', 'nullable' => true],
                            'ogrnip' => ['type' => 'string', 'nullable' => true],
                            'ogrn' => ['type' => 'string', 'nullable' => true],
                            'snils' => ['type' => 'string', 'nullable' => true],
                            'valid_from' => ['type' => 'string', 'nullable' => true],
                            'valid_to' => ['type' => 'string', 'nullable' => true],
                            'container' => ['type' => 'string', 'nullable' => true],
                            'last_used_at' => ['type' => 'string', 'nullable' => true],
                            'created_at' => ['type' => 'string', 'nullable' => true],
                            'updated_at' => ['type' => 'string', 'nullable' => true],
                        ],
                    ],
                    'SignatureListResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Signature'],
                            ],
                            'meta' => ['type' => 'object'],
                        ],
                    ],
                    'SignatureImportResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => '#/components/schemas/Signature'],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'import' => ['type' => 'object'],
                                    'count' => ['type' => 'integer'],
                                    'server_time' => ['type' => 'string', 'format' => 'date-time'],
                                ],
                            ],
                        ],
                    ],
                    'SignRequest' => [
                        'type' => 'object',
                        'required' => ['data'],
                        'properties' => [
                            'data' => ['type' => 'string'],
                            'data_encoding' => ['type' => 'string', 'enum' => ['utf8', 'base64'], 'default' => 'utf8'],
                            'detached' => ['type' => 'boolean', 'default' => false],
                        ],
                    ],
                    'SignResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => ['type' => 'object'],
                        ],
                    ],
                    'ErrorResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
