<?php
namespace App\Http\Requests\Tasks;
use Illuminate\Foundation\Http\FormRequest;
class UploadTaskAttachmentRequest extends FormRequest { public function authorize(): bool { return true; } public function rules(): array { return ['attachment' => ['required','file','max:10240','mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt']]; } }
