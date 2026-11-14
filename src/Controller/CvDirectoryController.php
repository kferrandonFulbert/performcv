<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\CvDirectoryRepository;
use App\Entity\CvViews;
use App\Service\{CvPresentation, DirectorySearch, PublicSeo, WorkspaceWriter};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response};
use Symfony\Component\Routing\Attribute\Route;

/**
 * Expose la recherche, les fiches publiques et les sitemaps des CV admissibles à l’annuaire.
 */
final class CvDirectoryController extends AbstractController
{
    #[Route('/annuaire-cv', name: 'app_cv_directory', methods: ['GET'])]
    public function index(Request $request, CvDirectoryRepository $directory, PublicSeo $seo): Response
    {
        $query = trim($request->query->getString('q'));
        $page = $request->query->getInt('page', 1);
        try {
            DirectorySearch::terms($query);
            $results = $directory->search($query, $page);
        } catch (\InvalidArgumentException $error) {
            return $this->render('directory/index.html.twig', ['query' => $query, 'page' => 1, 'items' => [], 'next' => false, 'error' => $error->getMessage(), 'public_canonical' => null], new Response(status: 400, headers: ['X-Robots-Tag' => 'noindex, follow']));
        }
        if ($page > 1 && !$results['items']) { throw $this->createNotFoundException(); }
        $response = $this->render('directory/index.html.twig', $results + [
            'query' => $query, 'page' => $page,
            'public_canonical' => $query === '' ? $seo->url('app_cv_directory', $page > 1 ? ['page' => $page] : []) : null,
        ]);
        if ($query !== '') {
            $response->headers->set('X-Robots-Tag', 'noindex, follow');
        }
        return $response;
    }

    #[Route('/cv-public/{id}', name: 'app_directory_cv', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, int $id, CvDirectoryRepository $directory, CvPresentation $presentation, PublicSeo $seo, WorkspaceWriter $writer): Response
    {
        $share = $directory->publishedShare($id) ?? throw $this->createNotFoundException();
        $cv = $share->getCvInstance();
        $response = $this->render('directory/cv.html.twig', $presentation->build($cv->getProfile(), $cv) + ['public_canonical' => $seo->url('app_directory_cv', ['id' => $id])]);
        if ($request->isMethod('GET')) {
            $writer->save((new CvViews())->setCvShare($share)->setViewedAt(new \DateTime()));
        }
        return $response;
    }

    #[Route('/sitemap.xml', name: 'app_sitemap', methods: ['GET'])]
    #[Route('/sitemap-cv.xml', name: 'app_directory_sitemap_index', methods: ['GET'])]
    public function sitemapIndex(CvDirectoryRepository $directory, PublicSeo $seo): Response
    {
        $urls = [$seo->url('app_sitemap_pages'), $seo->url('app_sitemap_guides')];
        for ($page = 1, $pages = (int) ceil($directory->count() / CvDirectoryRepository::SITEMAP_SIZE); $page <= $pages; ++$page) {
            $urls[] = $seo->url('app_directory_sitemap', ['page' => $page]);
        }
        return $this->render('seo/sitemap-index.xml.twig', ['urls' => $urls], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }

    #[Route('/sitemap-cv-{page}.xml', name: 'app_directory_sitemap', requirements: ['page' => '[1-9]\d{0,4}'], methods: ['GET'])]
    #[Route('/sitemap-cv/{page}.xml', name: 'app_directory_sitemap_legacy', requirements: ['page' => '[1-9]\d{0,4}'], methods: ['GET'])]
    public function sitemap(int $page, CvDirectoryRepository $directory, PublicSeo $seo): Response
    {
        $ids = $directory->sitemapIds($page);
        if (!$ids) { throw $this->createNotFoundException(); }
        $urls = array_map(fn ($id): string => $seo->url('app_directory_cv', ['id' => $id]), $ids);
        $offset = ($page - 1) * CvDirectoryRepository::SITEMAP_SIZE;
        for ($i = 0; $i < count($ids); $i += CvDirectoryRepository::PAGE_SIZE) {
            $directoryPage = 1 + intdiv($offset + $i, CvDirectoryRepository::PAGE_SIZE);
            if ($directoryPage > CvDirectoryRepository::MAX_PAGE) {
                break;
            }
            $urls[] = $seo->url('app_cv_directory', $directoryPage === 1 ? [] : ['page' => $directoryPage]);
        }

        return $this->render('seo/sitemap.xml.twig', ['urls' => $urls], new Response(headers: ['Content-Type' => 'application/xml; charset=UTF-8']));
    }
}
