<?php

namespace App\Domain\DataIngestion\Actions;

use App\Models\DataIngestion\DataSource;
use App\Models\User\User;

class AttachPublicDataSourceAction
{
    /**
     * Rattache une source publique au compte de l'utilisateur, sans dupliquer
     * ni fichier ni lignes Dataset/DatasetColumn/DatasetVersion : la source
     * reste une instance unique, partagée via la table pivot data_source_user.
     *
     * @throws \RuntimeException si la source n'est pas publique.
     */
    public function execute(DataSource $dataSource, User $user): DataSource
    {
        if ($dataSource->visibility !== 'public') {
            throw new \RuntimeException('Cette source n\'est pas publique.');
        }

        if ($dataSource->user_id === $user->id) {
            return $dataSource;
        }

        $dataSource->users()->syncWithoutDetaching([$user->id]);

        return $dataSource;
    }
}
