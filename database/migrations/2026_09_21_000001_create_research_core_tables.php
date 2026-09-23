<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->createCategories();
        $this->createDocuments();
        $this->createDocumentFiles();
        $this->createDocumentChunks();
        $this->createAiTools();
        $this->createPromptTemplates();
        $this->createAiToolPromptForeignKey();
        $this->createAiRuns();
        $this->createAiRunSources();
        $this->createAiRunResults();
        $this->createSavedResults();
        $this->createAuditLogs();
        $this->createWorkspaces();
        $this->createWorkspaceDocuments();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('workspace_documents');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('saved_results');
        Schema::dropIfExists('ai_run_results');
        Schema::dropIfExists('ai_run_sources');
        Schema::dropIfExists('ai_runs');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('ai_tools');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('document_files');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('categories');

        Schema::enableForeignKeyConstraints();
    }

    private function createCategories(): void
    {
        if (Schema::hasTable('categories')) {
            return;
        }

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 180);
            $table->string('slug', 190)->unique();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });
    }

    private function createDocuments(): void
    {
        if (Schema::hasTable('documents')) {
            return;
        }

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title');
            $table->longText('abstract')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('document_type', 100);
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->string('language', 20)->nullable();
            $table->string('doi')->nullable()->index();
            $table->string('isbn', 100)->nullable()->index();
            $table->string('publisher')->nullable();
            $table->string('source_label')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->string('visibility', 30)->default('restricted')->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamps();
        });
    }

    private function createDocumentFiles(): void
    {
        if (Schema::hasTable('document_files')) {
            return;
        }

        Schema::create('document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->string('disk', 80)->default('local');
            $table->string('path', 500);
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum', 128)->nullable()->index();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });
    }

    private function createDocumentChunks(): void
    {
        if (Schema::hasTable('document_chunks')) {
            return;
        }

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->longText('content');
            $table->json('metadata_json')->nullable();
            $table->timestamps();
            $table->unique(['document_id', 'chunk_index']);
        });
    }

    private function createAiTools(): void
    {
        if (Schema::hasTable('ai_tools')) {
            return;
        }

        Schema::create('ai_tools', function (Blueprint $table) {
            $table->id();
            $table->string('code', 120)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->string('icon', 40)->nullable();
            $table->unsignedBigInteger('active_prompt_template_id')->nullable()->index();
            $table->json('input_schema_json')->nullable();
            $table->json('output_schema_json')->nullable();
            $table->string('status', 30)->default('active')->index();
            $table->timestamps();
        });
    }

    private function createPromptTemplates(): void
    {
        if (Schema::hasTable('prompt_templates')) {
            return;
        }

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_tool_id')->nullable()->constrained('ai_tools')->nullOnDelete();
            $table->string('name', 180);
            $table->unsignedInteger('version')->default(1);
            $table->longText('system_prompt')->nullable();
            $table->longText('task_prompt');
            $table->longText('retrieval_instructions')->nullable();
            $table->longText('output_instructions')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['ai_tool_id', 'name', 'version'], 'prompt_templates_tool_name_version_unique');
        });
    }

    private function createAiToolPromptForeignKey(): void
    {
        if (! Schema::hasTable('ai_tools') || ! Schema::hasTable('prompt_templates')) {
            return;
        }

        // Fresh installs receive the FK. Existing installations may already
        // have the column/constraint, so avoid altering an established schema.
        if (! Schema::hasColumn('ai_tools', 'active_prompt_template_id')) {
            Schema::table('ai_tools', function (Blueprint $table) {
                $table->foreignId('active_prompt_template_id')->nullable()->after('icon')->index();
            });
        }
    }

    private function createAiRuns(): void
    {
        if (Schema::hasTable('ai_runs')) {
            return;
        }

        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_tool_id')->nullable()->constrained('ai_tools')->nullOnDelete();
            $table->foreignId('prompt_template_id')->nullable()->constrained('prompt_templates')->nullOnDelete();
            $table->string('status', 30)->default('queued')->index();
            $table->json('user_parameters_json')->nullable();
            $table->string('model_provider', 120)->nullable();
            $table->string('model_name', 180)->nullable();
            $table->decimal('temperature', 4, 2)->nullable();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    private function createAiRunSources(): void
    {
        if (Schema::hasTable('ai_run_sources')) {
            return;
        }

        Schema::create('ai_run_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['ai_run_id', 'document_id']);
        });
    }

    private function createAiRunResults(): void
    {
        if (Schema::hasTable('ai_run_results')) {
            return;
        }

        Schema::create('ai_run_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->string('result_type', 60)->default('primary')->index();
            $table->longText('content')->nullable();
            $table->json('structured_output_json')->nullable();
            $table->json('citations_json')->nullable();
            $table->timestamps();
        });
    }

    private function createSavedResults(): void
    {
        if (Schema::hasTable('saved_results')) {
            return;
        }

        Schema::create('saved_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ai_run_id')->constrained('ai_runs')->cascadeOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->json('tags_json')->nullable();
            $table->boolean('is_favorite')->default(false)->index();
            $table->timestamps();
        });
    }

    private function createAuditLogs(): void
    {
        if (Schema::hasTable('audit_logs')) {
            return;
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 180)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata_json')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    private function createWorkspaces(): void
    {
        if (Schema::hasTable('workspaces')) {
            return;
        }

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['user_id', 'is_default']);
        });
    }

    private function createWorkspaceDocuments(): void
    {
        if (Schema::hasTable('workspace_documents')) {
            return;
        }

        Schema::create('workspace_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['workspace_id', 'document_id']);
        });
    }
};
