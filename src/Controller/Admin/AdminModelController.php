<?php

namespace App\Controller\Admin;

use App\Entity\ModelProfile;
use App\Repository\ModelProfileRepository;
use App\Service\ImageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/models')]
#[IsGranted('ROLE_ADMIN')]
class AdminModelController extends AbstractController
{
    public function __construct(
        private ModelProfileRepository $modelRepository,
        private EntityManagerInterface $em,
        private ImageService $imageService
    ) {
    }

    #[Route('', name: 'admin_models')]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = $request->query->getInt('per_page', 30);
        
        $allowedPerPage = [15, 25, 50, 100];
        if (!in_array($perPage, $allowedPerPage)) {
            $perPage = 30;
        }
        
        $limit = $perPage;
        
        $qb = $this->modelRepository->createQueryBuilder('m')
            ->orderBy('m.videosCount', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);
        
        $models = $qb->getQuery()->getResult();
        $total = $this->modelRepository->count([]);
        
        return $this->render('admin/models/index.html.twig', [
            'models' => $models,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'pages' => ceil($total / $limit),
        ]);
    }

    #[Route('/new', name: 'admin_models_new')]
    public function new(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, new ModelProfile());
        }

        return $this->render('admin/models/form.html.twig', [
            'model' => new ModelProfile(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_models_edit')]
    public function edit(Request $request, ModelProfile $model): Response
    {
        if ($request->isMethod('POST')) {
            return $this->handleSave($request, $model);
        }

        return $this->render('admin/models/form.html.twig', [
            'model' => $model,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_models_delete', methods: ['POST'])]
    public function delete(ModelProfile $model): Response
    {
        $this->em->remove($model);
        $this->em->flush();
        
        $this->addFlash('success', 'Модель удалена');
        return $this->redirectToRoute('admin_models');
    }

    #[Route('/bulk', name: 'admin_models_bulk', methods: ['POST'])]
    public function bulk(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('bulk_models', $request->request->get('_token'))) {
            $this->addFlash('error', 'Недействительный токен безопасности');
            return $this->redirectToRoute('admin_models');
        }

        $modelIds = $request->request->all('model_ids');
        $action = $request->request->get('bulk_action');

        if (empty($modelIds)) {
            $this->addFlash('error', 'Не выбрано ни одной модели');
            return $this->redirectToRoute('admin_models');
        }

        if (empty($action)) {
            $this->addFlash('error', 'Не выбрано действие');
            return $this->redirectToRoute('admin_models');
        }

        $models = $this->modelRepository->findBy(['id' => $modelIds]);
        $count = count($models);

        switch ($action) {
            case 'verify':
                foreach ($models as $model) {
                    $model->setVerified(true);
                }
                $this->em->flush();
                $this->addFlash('success', "Верифицировано моделей: {$count}");
                break;

            case 'unverify':
                foreach ($models as $model) {
                    $model->setVerified(false);
                }
                $this->em->flush();
                $this->addFlash('success', "Снята верификация у моделей: {$count}");
                break;

            case 'premium':
                foreach ($models as $model) {
                    $model->setPremium(true);
                }
                $this->em->flush();
                $this->addFlash('success', "Выдан премиум моделям: {$count}");
                break;

            case 'remove_premium':
                foreach ($models as $model) {
                    $model->setPremium(false);
                }
                $this->em->flush();
                $this->addFlash('success', "Снят премиум у моделей: {$count}");
                break;

            case 'activate':
                foreach ($models as $model) {
                    $model->setActive(true);
                }
                $this->em->flush();
                $this->addFlash('success', "Активировано моделей: {$count}");
                break;

            case 'deactivate':
                foreach ($models as $model) {
                    $model->setActive(false);
                }
                $this->em->flush();
                $this->addFlash('success', "Деактивировано моделей: {$count}");
                break;

            case 'delete':
                foreach ($models as $model) {
                    $this->em->remove($model);
                }
                $this->em->flush();
                $this->addFlash('success', "Удалено моделей: {$count}");
                break;

            default:
                $this->addFlash('error', 'Неизвестное действие');
        }

        return $this->redirectToRoute('admin_models');
    }

    #[Route('/create-ajax', name: 'admin_models_create_ajax', methods: ['POST'])]
    public function createAjax(Request $request): Response
    {
        $data = json_decode($request->getContent(), true);
        $modelName = trim($data['name'] ?? '');
        
        if (empty($modelName)) {
            return $this->json(['success' => false, 'error' => 'Имя модели не может быть пустым']);
        }
        
        // Проверяем, существует ли уже такая модель
        $existingModel = $this->modelRepository->findOneBy(['displayName' => $modelName]);
        if ($existingModel) {
            return $this->json([
                'success' => true,
                'model' => [
                    'id' => $existingModel->getId(),
                    'name' => $existingModel->getDisplayName(),
                    'gender' => $existingModel->getGender()
                ]
            ]);
        }
        
        // Создаем новую модель
        $model = new ModelProfile();
        $model->setDisplayName($modelName);
        $model->setGender('female'); // По умолчанию
        $model->setActive(true);
        $model->setVerified(false);
        $model->setPremium(false);
        
        // Генерация уникального slug
        $slug = $this->generateSlug($modelName);
        $slug = $this->ensureUniqueSlug($slug);
        $model->setSlug($slug);
        
        $this->em->persist($model);
        $this->em->flush();
        
        return $this->json([
            'success' => true,
            'model' => [
                'id' => $model->getId(),
                'name' => $model->getDisplayName(),
                'gender' => $model->getGender()
            ]
        ]);
    }


    private function handleSave(Request $request, ModelProfile $model): Response
    {
        // Основная информация
        $displayName = trim($request->request->get('display_name', ''));
        $model->setDisplayName($displayName);
        $model->setBio($request->request->get('bio'));
        $model->setGender($request->request->get('gender', 'female'));
        
        // Slug - автогенерация если не указан
        $slug = trim($request->request->get('slug', ''));
        if (empty($slug)) {
            $slug = $this->generateSlug($displayName);
        }
        // Проверяем уникальность slug
        $slug = $this->ensureUniqueSlug($slug, $model->getId());
        $model->setSlug($slug);
        
        // Псевдонимы
        $aliasesString = trim($request->request->get('aliases', ''));
        $model->setAliasesFromString($aliasesString);
        
        // Дата рождения и возраст
        $birthDateStr = $request->request->get('birth_date');
        if (!empty($birthDateStr)) {
            $birthDate = new \DateTime($birthDateStr);
            $model->setBirthDate($birthDate);
            // Автоматически вычисляем возраст
            $age = $birthDate->diff(new \DateTime())->y;
            $model->setAge($age);
        } else {
            $model->setBirthDate(null);
            // Если дата рождения не указана, используем введённый возраст
            $age = $request->request->get('age');
            $model->setAge($age ? (int)$age : null);
        }
        
        // Локация и этничность
        $model->setCountry($request->request->get('country') ?: null);
        $model->setEthnicity($request->request->get('ethnicity') ?: null);
        
        // Начало карьеры
        $careerStartStr = $request->request->get('career_start');
        if (!empty($careerStartStr)) {
            $model->setCareerStart(new \DateTime($careerStartStr));
        } else {
            $model->setCareerStart(null);
        }
        
        // Внешность
        $model->setHairColor($request->request->get('hair_color') ?: null);
        $model->setEyeColor($request->request->get('eye_color') ?: null);
        
        $height = $request->request->get('height');
        $model->setHeight($height ? (int)$height : null);
        
        $weight = $request->request->get('weight');
        $model->setWeight($weight ? (int)$weight : null);
        
        $model->setBreastSize($request->request->get('breast_size') ?: null);
        $model->setHasTattoos($request->request->getBoolean('has_tattoos'));
        $model->setHasPiercings($request->request->getBoolean('has_piercings'));
        
        // Статусы
        $model->setActive($request->request->getBoolean('is_active'));
        $model->setVerified($request->request->getBoolean('is_verified'));
        $model->setPremium($request->request->getBoolean('is_premium'));
        
        // SEO поля
        $model->setMetaTitle($request->request->get('meta_title') ?: null);
        $model->setMetaDescription($request->request->get('meta_description') ?: null);
        $model->setMetaKeywords($request->request->get('meta_keywords') ?: null);
        
        // Обработка загрузки аватара
        $avatarFile = $request->files->get('avatar');
        if ($avatarFile) {
            $newAvatar = $this->imageService->processAvatar($avatarFile);
            $model->setAvatar($newAvatar);
        }
        
        // Обработка загрузки обложки
        $coverFile = $request->files->get('cover_photo');
        if ($coverFile) {
            $newCover = $this->imageService->processCover($coverFile);
            $model->setCoverPhoto($newCover);
        }
        
        // Обновляем updatedAt
        $model->setUpdatedAt(new \DateTimeImmutable());
        
        $this->em->persist($model);
        $this->em->flush();
        
        $this->addFlash('success', 'Модель сохранена');
        return $this->redirectToRoute('admin_models');
    }
    
    /**
     * Генерирует slug из имени модели
     * Slug должен быть валидным URL-совместимым значением
     * (только латинские буквы, цифры и дефисы, в нижнем регистре)
     */
    public function generateSlug(string $name): string
    {
        $slugger = new AsciiSlugger();
        return (string) $slugger->slug($name)->lower();
    }
    
    /**
     * Проверяет уникальность slug и добавляет суффикс при необходимости
     */
    private function ensureUniqueSlug(string $slug, ?int $excludeId = null): string
    {
        $originalSlug = $slug;
        $counter = 1;
        
        while (true) {
            $qb = $this->modelRepository->createQueryBuilder('m')
                ->where('m.slug = :slug')
                ->setParameter('slug', $slug);
            
            if ($excludeId) {
                $qb->andWhere('m.id != :id')
                   ->setParameter('id', $excludeId);
            }
            
            $existing = $qb->getQuery()->getOneOrNullResult();
            
            if (!$existing) {
                return $slug;
            }
            
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
    }
}
