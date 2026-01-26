<?php

namespace App\Controller;

use App\Repository\ModelLikeRepository;
use App\Repository\ModelProfileRepository;
use App\Repository\ModelSubscriptionRepository;
use App\Service\ModelStatsService;
use App\Service\SeeAlsoService;
use App\Service\SettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/models')]
class ModelController extends AbstractController
{
    public function __construct(
        private ModelProfileRepository $modelRepository,
        private ModelStatsService $modelStatsService,
        private ModelSubscriptionRepository $subscriptionRepository,
        private ModelLikeRepository $likeRepository,
        private SeeAlsoService $seeAlsoService,
        private SettingsService $settingsService
    ) {
    }

    /**
     * Список моделей с пагинацией, сортировкой, поиском и фильтрацией
     * Requirements: 1.1, 1.2, 1.3, 1.4, 1.5
     */
    #[Route('/', name: 'app_models')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $search = $request->query->get('search');
        $sort = $request->query->get('sort', 'popular');
        $gender = $request->query->get('gender');

        // Валидация параметра сортировки
        $allowedSorts = ['popular', 'newest', 'alphabetical', 'videos'];
        if (!\in_array($sort, $allowedSorts, true)) {
            $sort = 'popular';
        }

        // Валидация параметра пола
        $allowedGenders = ['male', 'female', 'trans'];
        if ($gender !== null && !\in_array($gender, $allowedGenders, true)) {
            $gender = null;
        }

        $result = $this->modelRepository->findPaginated($page, $limit, $search, $sort, $gender);

        return $this->render('model/index.html.twig', [
            'models' => $result['items'],
            'total' => $result['total'],
            'pages' => $result['pages'],
            'page' => $page,
            'search' => $search,
            'sort' => $sort,
            'gender' => $gender,
        ]);
    }


    /**
     * Профиль модели с видео
     * Requirements: 2.1, 2.2, 2.3, 2.4, 2.5, 2.6
     */
    #[Route('/{slug}', name: 'app_model_show')]
    public function show(string $slug, Request $request): Response
    {
        $model = $this->modelRepository->findBySlug($slug);

        if (!$model) {
            throw new NotFoundHttpException('Модель не найдена');
        }

        // Увеличиваем счётчик просмотров с защитой от накрутки
        $session = $request->getSession();
        $this->modelStatsService->incrementViewCount($model, $this->getUser(), $session);

        // Пагинация видео
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $this->settingsService->getVideosPerPage();
        $offset = ($page - 1) * $limit;

        $videosResult = $this->modelRepository->findActiveWithVideos($model->getId(), $limit, $offset);
        $videosPages = (int) \ceil($videosResult['total'] / $limit);

        // Вычисляем знак зодиака если есть дата рождения
        $zodiacSign = null;
        $calculatedAge = null;
        if ($model->getBirthDate()) {
            $zodiacSign = $this->modelStatsService->getZodiacSign($model->getBirthDate());
            $calculatedAge = $this->modelStatsService->calculateAge($model->getBirthDate());
        }

        // Проверяем подписку текущего пользователя
        $isSubscribed = false;
        $userLikeType = null;
        $user = $this->getUser();
        if ($user) {
            $isSubscribed = $this->subscriptionRepository->isSubscribed($user, $model);
            $userLikeType = $this->likeRepository->getUserLikeType($user, $model);
        }

        // Блок "Смотрите также"
        $seeAlso = [
            'other_videos' => $this->seeAlsoService->getOtherVideosForModel($model, null, 6),
            'related_categories' => $this->seeAlsoService->getRelatedCategoriesForModel($model, 6),
            'related_tags' => $this->seeAlsoService->getRelatedTagsForModel($model, 10),
        ];

        return $this->render('model/show.html.twig', [
            'model' => $model,
            'videos' => $videosResult['items'],
            'videosTotal' => $videosResult['total'],
            'videosPages' => $videosPages,
            'page' => $page,
            'zodiacSign' => $zodiacSign,
            'calculatedAge' => $calculatedAge,
            'is_subscribed' => $isSubscribed,
            'user_like_type' => $userLikeType,
            'see_also' => $seeAlso,
        ]);
    }
}
