<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CvShares;
use App\Repository\{CvDirectoryRepository, WorkspaceRepository};

final class CvSeo
{
    public function __construct(private PublicSeo $seo, private CvDirectoryRepository $directory, private WorkspaceRepository $workspace, private ShareAccess $access)
    {
    }

    /** The caller must first verify access to the requested share and its template. */
    public function canonical(CvShares $share): ?string
    {
        if (!$this->access->active($share) || $this->access->needsPassword($share) || $share->getPasswordHash() !== null) {
            return null;
        }
        $id = $share->getCvInstanceId();
        if ($this->directory->publishedShare($id) !== null) {
            return $this->seo->url('app_directory_cv', ['id' => $id]);
        }
        $preferred = $this->workspace->preferredPublicShare($id);

        return $preferred ? $this->seo->url('app_public_share', ['token' => $preferred->getShareToken()]) : null;
    }
}
