<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'participants' => ['view' => 'Visualizar participantes', 'create' => 'Cadastrar participantes', 'edit' => 'Editar participantes', 'delete' => 'Excluir participantes'],
            'meetings' => ['view' => 'Visualizar reuniões', 'create' => 'Cadastrar reuniões', 'edit' => 'Editar reuniões', 'delete' => 'Excluir reuniões', 'finish' => 'Finalizar reuniões'],
            'projects' => ['view' => 'Visualizar projetos', 'create' => 'Cadastrar projetos'],
            'reports' => ['view' => 'Visualizar relatórios', 'export' => 'Exportar relatórios'],
            'users' => ['administer' => 'Administrar usuários'],
            'job_positions' => ['view' => 'Visualizar cargos', 'create' => 'Cadastrar cargos', 'edit' => 'Editar cargos', 'deactivate' => 'Inativar cargos'],
            'access_profiles' => ['view' => 'Visualizar perfis', 'create' => 'Cadastrar perfis', 'edit' => 'Editar perfis', 'administer' => 'Administrar permissões'],
            'teams' => ['view' => 'Visualizar equipes', 'create' => 'Cadastrar equipes'],
        ];

        foreach ($permissions as $resource => $actions) {
            foreach ($actions as $action => $description) {
                Permission::updateOrCreate(['resource' => $resource, 'action' => $action], ['name' => $resource.'.'.$action, 'description' => $description]);
            }
        }
    }
}
