<?php

namespace App\Http\Controllers;

use App\UseCases\Spj\CreateSpjDraftUseCase;
use Illuminate\Http\RedirectResponse;

class SpjPreparationController extends Controller
{
    public function __invoke(string $transactionId, CreateSpjDraftUseCase $useCase): RedirectResponse
    {
        return $useCase->handle($transactionId);
    }
}
