<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Repository\TagRepository;
use App\Repository\VideoRepository;
use App\Service\SeeAlsoService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tags')]
class TagController extends AbstractController
{
    #[Route('', name: 'app_tags')]
    public function index(Request $request, TagRepository $tagRepository): Response
    {
        $sort = $request->query->get('sort', 'name');
        $tags = $tagRepository->findAllSorted($sort);

        return $this->render('tag/index.html.twig', [
            'tags' => $tags,
            'sort' => $sort,
        ]);
    }

    #[Route('/{slug}', name: 'app_tag_show')]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Tag $tag,
        Request $request,
        VideoRepository $videoRepository,
        SeeAlsoService $seeAlsoService
    ): Response {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 24;
        $offset = ($page - 1) * $limit;

        $videos = $videoRepository->findByTag($tag->getId(), $limit, $offset);
        $videosCount = $videoRepository->countByTag($tag->getId());

        // Блок "Смотрите также"
        $seeAlso = [
            'related_categories' => $seeAlsoService->getRelatedCategoriesForTag($tag, 6),
            'related_models' => $seeAlsoService->getRelatedModelsForTag($tag, 8),
        ];

        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
            'videos' => $videos,
            'videos_count' => $videosCount,
            'page' => $page,
            'total_pages' => ceil($videosCount / $limit),
            'see_also' => $seeAlso,
        ]);
    }
}