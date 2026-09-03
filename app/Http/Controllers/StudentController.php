<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'source' => ['nullable', 'in:DAPODIK,MANUAL'], 'class' => ['nullable', 'string', 'max:100'], 'perPage' => ['nullable', 'in:15,25,50,100']]);
        $students = Student::query()->search($filters['q'] ?? null)
            ->when($filters['source'] ?? null, fn ($q, $v) => $q->where('source_type', $v))
            ->when($filters['class'] ?? null, fn ($q, $v) => $q->where('class_name', $v))
            ->orderByDesc('is_active')->orderBy('class_name')->orderBy('name')->paginate((int) ($filters['perPage'] ?? 15))->withQueryString();
        $classes = Student::whereNotNull('class_name')->distinct()->orderBy('class_name')->pluck('class_name');
        $summary = ['total' => Student::count(), 'active' => Student::where('is_active', true)->count(), 'dapodik' => Student::where('source_type', 'DAPODIK')->count(), 'manual' => Student::where('source_type', 'MANUAL')->count()];

        return view('students.index', compact('students', 'classes', 'filters', 'summary'));
    }

    public function show(int $studentId): View
    {
        return view('students.show', ['student' => Student::findOrFail($studentId)]);
    }

    public function create(): View
    {
        return view('students.form', ['student' => new Student]);
    }

    public function edit(int $studentId): View
    {
        return view('students.form', ['student' => Student::findOrFail($studentId)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $student = new Student;
        $this->persist($request, $student);

        return redirect()->route('students.show', $student)->with('success', 'Siswa berhasil ditambahkan.');
    }

    public function update(Request $request, int $studentId): RedirectResponse
    {
        $student = Student::findOrFail($studentId);
        $this->persist($request, $student);

        return redirect()->route('students.show', $student)->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(int $studentId): RedirectResponse
    {
        $student = Student::findOrFail($studentId);
        if ($student->source_type !== 'MANUAL') {
            return back()->with('error', 'Data Dapodik tidak dapat dihapus dari aplikasi.');
        } $student->delete();

        return redirect()->route('students.index')->with('success', 'Siswa manual berhasil dihapus.');
    }

    private function persist(Request $request, Student $student): void
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'nisn' => ['nullable', 'string', 'max:20', Rule::unique('school.students', 'nisn')->ignore($student->id)], 'nipd' => ['nullable', 'string', 'max:30'],
            'nik' => ['nullable', 'string', 'max:30'], 'gender' => ['nullable', 'in:L,P'], 'birth_place' => ['nullable', 'string', 'max:100'], 'birth_date' => ['nullable', 'date'],
            'religion' => ['nullable', 'string', 'max:50'], 'address' => ['nullable', 'string', 'max:500'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'],
            'father_name' => ['nullable', 'string', 'max:160'], 'mother_name' => ['nullable', 'string', 'max:160'], 'guardian_name' => ['nullable', 'string', 'max:160'],
            'class_name' => ['nullable', 'string', 'max:100'], 'grade_level' => ['nullable', 'string', 'max:20'], 'registration_type' => ['nullable', 'string', 'max:100'], 'previous_school' => ['nullable', 'string', 'max:160'], 'school_entry_date' => ['nullable', 'date'],
            'child_order' => ['nullable', 'integer', 'min:1'], 'height' => ['nullable', 'numeric', 'min:0'], 'weight' => ['nullable', 'numeric', 'min:0'], 'special_needs' => ['nullable', 'boolean'], 'is_active' => ['nullable', 'boolean'],
        ]);
        $student->fill($data + ['source_type' => $student->exists ? $student->source_type : 'MANUAL', 'source_key' => $student->exists ? $student->source_key : 'MANUAL:'.Str::uuid()]);
        $student->normalized_name = Str::of($data['name'])->lower()->ascii()->replaceMatches('/[^a-z0-9]+/', ' ')->squish();
        $student->is_active = $request->boolean('is_active', true);
        $student->special_needs = $request->boolean('special_needs');
        $student->save();
    }
}
