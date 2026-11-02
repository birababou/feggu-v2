<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Models\Ref\Commune;
use App\Models\Ref\Departement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PageController extends Controller
{
    public function confidentialite()
    {
        return Inertia::render('Public/Confidentialite');
    }

    public function aide()
    {
        return Inertia::render('Public/Aide', [
            'organisations' => Organisation::orderBy('nom')->get(['nom', 'sigle', 'services']),
        ]);
    }

    public function langue(Request $request, string $langue)
    {
        abort_unless(in_array($langue, ['fr', 'wo'], true), 404);
        $request->session()->put('langue', $langue);

        return back();
    }

    public function departements(Request $request)
    {
        return Departement::where('region_id', (int) $request->query('region'))->orderBy('nom')->get(['id', 'nom']);
    }

    public function communes(Request $request)
    {
        return Commune::where('departement_id', (int) $request->query('departement'))->orderBy('nom')->get(['id', 'nom']);
    }
}
