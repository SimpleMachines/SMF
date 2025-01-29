<?php

declare(strict_types=1);

namespace SMF\Handler;

use GlobIterator;
use Laminas\Diactoros\Response\JsonResponse;
use Mezzio\Router\RouteResult;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

use function sprintf;

use const JSON_PRETTY_PRINT;

final class UtilityHandler implements RequestHandlerInterface
{
	private const ACTION_DIRECTORIES = ['Admin', 'Moderation', 'Profile'];
	private const ACTION_DIR_PATTERN = __DIR__ . '/../Actions/%s/*.php';
	private const ACTION_PATTERN = __DIR__ . '/../Actions/*.php';

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
		$routeResult = $request->getAttribute(RouteResult::class);
		return match ($routeResult->getMatchedParams()['action']) {
			'build-action-tree' => $this->buildActionTree(),
			default => throw new RuntimeException('Invalid action'),
		};
    }

	private function buildActionTree(): ResponseInterface
	{
		$data = ['running' => 'build-action-tree'];

		foreach (self::ACTION_DIRECTORIES as $directory) {
			$iterator = new GlobIterator(sprintf(self::ACTION_DIR_PATTERN, $directory));

			while ($iterator->valid()) {
				$className = $iterator->current()->getBasename('.php');
				$subactions = function() use ($directory, $className) {
					$fqcn = 'SMF\\Actions\\' . $directory . '\\' . $className;
					return isset($fqcn::$subactions) ? $fqcn::$subactions : [];
				};
				$data['directories'][$directory][$className] = $subactions();
				$iterator->next();
			}
		}

		$iterator = new GlobIterator(self::ACTION_PATTERN);

		while ($iterator->valid()) {
			$className = $iterator->current()->getBasename('.php');
			$subactions = function() use ( $className) {
				$fqcn = 'SMF\\Actions\\' . $className;
				return isset($fqcn::$subactions) ? $fqcn::$subactions : [];
			};

			$data['files'][$className] = $subactions();
			$iterator->next();
		}

		return new JsonResponse(
			data: $data,
			encodingOptions: JsonResponse::DEFAULT_JSON_FLAGS | JSON_PRETTY_PRINT
		);
	}
}
