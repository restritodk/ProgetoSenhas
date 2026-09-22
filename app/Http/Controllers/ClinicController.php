<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateClinicRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClinicController extends Controller
{
    public function show(Request $request): View
    {
        $clinic = $request->user()->clinic;

        abort_if($clinic === null, 404);

        $this->authorize('manage', $clinic);

        return view('clinic.show', [
            'clinic' => $clinic,
        ]);
    }

    public function update(UpdateClinicRequest $request): RedirectResponse
    {
        $clinic = $request->user()->clinic;

        abort_if($clinic === null, 404);

        $clinic->update($request->safe()->only(['name', 'slug']));

        return redirect()
            ->route('clinic.show')
            ->with('status', 'Dados da clínica atualizados com sucesso.');
    }
}
