<?php

namespace Pterodactyl\Http\Controllers\Api\Client\Servers;

use Pterodactyl\Models\Server;
use Pterodactyl\Http\Controllers\Api\Client\ClientApiController;
use Pterodactyl\Http\Controllers\Api\Client\Servers\Traits\ProxiesDaemonController;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ListFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\GetFileContentsRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\WriteFileContentRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CreateFolderRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\RenameFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CopyFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\CompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DecompressFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\DeleteFileRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\ChmodFilesRequest;
use Pterodactyl\Http\Requests\Api\Client\Servers\Files\PullFileRequest;

class FileController extends ClientApiController
{
    use ProxiesDaemonController;

    public function directory(ListFilesRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'directory', func_get_args());
    }

    public function contents(GetFileContentsRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'contents', func_get_args());
    }

    public function download(GetFileContentsRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'download', func_get_args());
    }

    public function write(WriteFileContentRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'write', func_get_args());
    }

    public function create(CreateFolderRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'create', func_get_args());
    }

    public function rename(RenameFileRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'rename', func_get_args());
    }

    public function copy(CopyFileRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'copy', func_get_args());
    }

    public function compress(CompressFilesRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'compress', func_get_args());
    }

    public function decompress(DecompressFilesRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'decompress', func_get_args());
    }

    public function delete(DeleteFileRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'delete', func_get_args());
    }

    public function chmod(ChmodFilesRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'chmod', func_get_args());
    }

    public function pull(PullFileRequest $request, Server $server)
    {
        return $this->proxyToDaemonController('FileController', 'pull', func_get_args());
    }

    public function __call($method, $parameters)
    {
        return $this->proxyToDaemonController('FileController', $method, $parameters);
    }
}
