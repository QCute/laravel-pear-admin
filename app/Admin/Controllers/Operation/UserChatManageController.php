<?php

namespace App\Admin\Controllers\Operation;

use App\Admin\Builders\Form;
use App\Admin\Builders\Table;
use App\Admin\Builders\Table\Header;
use App\Admin\Builders\Table\Page;
use App\Admin\Controllers\Controller;
use App\Admin\Models\Model;
use App\Admin\Models\Operation\UserChatManageModel;
use App\Admin\Services\Auth\AuthService;
use App\Admin\Services\Extend\ChannelService;
use App\Admin\Services\Extend\MachineService;
use App\Admin\Services\Extend\ServerService;
use App\Admin\Traits\FormOutput;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class UserChatManageController extends Controller
{
    use FormOutput;

    public function index(Request $request)
    {
        $form = new Form();
        $form->hide();

        $columns = Schema::connection('admin')->getColumns(config('admin.database.user_chat_manage_table'));

        $columns = collect($columns)->map(function($item) { return (object)$item; });

        $form = $this->buildIndex($form, $columns);

        $header = $columns
            ->filter(function($item) {
                return $item->name != Model::DELETED_AT;
            })
            ->map(function($item) {
                return (new Header())->field($item->name)->title($item->comment ?? $item->name)->align()->minWidth(160);
            });

        $header->push((new Header())->field('')->title(trans('admin.table.operate'))->align()->width(160)->toolbar());

        $page = $request->input('page', 1);
        $perPage = $request->input('perPage', 10);
        $input = $request->except(['_token', 'page', 'perPage']);
        $paginator = UserChatManageModel::getPage($page, $perPage, $input);
        $data = $paginator->items();

        $paginate = (new Page())
            ->total($paginator->total())
            ->current($page)
            ->limit($perPage);

        return (new Table())
            ->form($form)
            ->header($header)
            ->data($data)
            ->right(['filter', 'create', 'download', 'export', 'search'])
            ->operation(['show', 'delete'])
            ->paginate($paginate)
            ->build();
    }

    public function create(Request $request)
    {
        $form = new Form();
        $form->name(trans('admin.form.create'));

        $select = $form->select('server')->label(trans('admin.server'))->required();
        $options = [
            'SERVER' => trans('admin.current.server'),
            'CHANNEL' => trans('admin.current.channel'),
            'ALL' => trans('admin.all'),
        ];
        foreach($options as $option => $name) {
            $select->option()->label($name)->value($option);
        }

        $form->textArea('role')->label(trans('admin.role'))->required();

        $radio = $form->checkBox('type')->label(trans('admin.chat.status'))->required();
        $options = [
            'normal' => trans('admin.chat.status.normal'),
            'ban' => trans('admin.chat.status.ban'),
            'ban-world' => trans('admin.chat.status.ban.world'),
            'ban-guild' => trans('admin.chat.status.ban.guild'),
            'ban-scene' => trans('admin.chat.status.ban.scene'),
            'ban-private' => trans('admin.chat.status.ban.private'),
        ];
        foreach($options as $option => $name) {
            $radio->option()->label($name)->value($option);
        }

        $form->dateTime('time')->label(trans('admin.time'))->required();

        return $form->build();
    }

    public function store(Request $request)
    {
        $server = $request->input('server');

        $roles = collect(explode("\n", $request->input('role')))
            ->map(function($role) { 
                return trim($role);
            })
            ->filter(function($role) {
                return $role !== '';
            })
            ->map(function($role) {
                return intval($role); 
            });

        $operations = $request->input('operations');

        try {
            $command = ($roles->isEmpty() ? 'server' : 'role') . '/' . 'chat';
            $data = [
                'roles' => $roles, 
                'operations' => $operations
            ];
            MachineService::send($server, $command, $data);

            $attributes = [
                'user_id' => AuthService::user()->id,
                'servers' => $server,
                'roles' => $roles,
                'operations' => $operations,
            ];
    
            UserChatManageModel::create($attributes);

        } catch (Exception $e) {
            return ['code' => $e->getCode(), 'msg' => $e->getMessage()];
        }

        return ['code' => 0, 'msg' => ''];
    }

    public function destroy(Request $request)
    {
        return ['code' => 0, 'msg' => ''];
    }
}
