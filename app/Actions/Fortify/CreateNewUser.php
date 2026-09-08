<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\ApprovalStatus;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Accepted ID proof formats, mirrored in the register form's accept attribute.
     *
     * @var list<string>
     */
    public const ID_PROOF_TYPES = ['jpeg', 'jpg', 'png', 'webp', 'pdf'];

    public const ID_PROOF_MAX_KILOBYTES = 10 * 1024;

    /**
     * Validate and create a newly registered user.
     *
     * Registrations land inactive and pending: the Executive Officer clears
     * them from the CEO queue before they can sign in.
     *
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            // Mirrors Department::selectable(), which populates the dropdown.
            // A boolean passed to Exists::where() stringifies to '', so the
            // constraint has to be built as a query.
            'department_id' => [
                'required',
                Rule::exists(Department::class, 'id')->where(
                    fn (Builder $query) => $query->where('is_active', true)->where('is_archived', false),
                ),
            ],
            'position_id' => ['required', Rule::exists(Position::class, 'id')],
            'employee_id' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'id_proofs' => ['required', 'array', 'min:1', 'max:5'],
            'id_proofs.*' => [
                File::types(self::ID_PROOF_TYPES)->max(self::ID_PROOF_MAX_KILOBYTES),
            ],
        ], [
            'id_proofs.required' => 'Upload at least one government-issued ID.',
            'id_proofs.*.max' => 'Each ID file must not be larger than 10 MB.',
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'department_id' => $input['department_id'],
                'position_id' => $input['position_id'],
                'employee_id' => $input['employee_id'] ?? null,
                'phone' => $input['phone'] ?? null,
            ]);

            $user->forceFill([
                'is_active' => false,
                'approval_status' => ApprovalStatus::Pending,
            ])->save();

            $this->storeIdProofs($user, $input['id_proofs']);

            return $user;
        });
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    private function storeIdProofs(User $user, array $files): void
    {
        $directory = 'user-id-proofs/'.now()->format('Y/m');

        foreach ($files as $file) {
            $path = $file->store($directory);

            $user->documents()->create([
                'document_type' => 'other',
                'title' => 'ID proof — '.$file->getClientOriginalName(),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $user->id,
                'status' => 'pending_review',
            ]);
        }
    }
}
