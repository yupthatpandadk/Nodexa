<?php

use Illuminate\Support\Facades\Route;
use Pterodactyl\Http\Controllers\Admin\SftpServerController;

Route::prefix('sftp-servers')->group(function () {
    Route::get('/', [SftpServerController::class, 'index'])->name('admin.sftp-servers');
    Route::post('/', [SftpServerController::class, 'store'])->name('admin.sftp-servers.store');
    Route::patch('/{sftpServer}', [SftpServerController::class, 'update'])->name('admin.sftp-servers.update');
    Route::delete('/{sftpServer}', [SftpServerController::class, 'destroy'])->name('admin.sftp-servers.delete');
    Route::post('/{sftpServer}/test', [SftpServerController::class, 'test'])->name('admin.sftp-servers.test');

    Route::get('/{sftpServer}/files', [SftpServerController::class, 'files'])->name('admin.sftp-servers.files');
    Route::post('/{sftpServer}/files/save', [SftpServerController::class, 'save'])->name('admin.sftp-servers.files.save');
    Route::post('/{sftpServer}/files/file', [SftpServerController::class, 'createFile'])->name('admin.sftp-servers.files.create-file');
    Route::post('/{sftpServer}/files/folder', [SftpServerController::class, 'createFolder'])->name('admin.sftp-servers.files.create-folder');
    Route::post('/{sftpServer}/files/rename', [SftpServerController::class, 'rename'])->name('admin.sftp-servers.files.rename');
    Route::post('/{sftpServer}/files/delete', [SftpServerController::class, 'delete'])->name('admin.sftp-servers.files.delete');
    Route::post('/{sftpServer}/files/upload', [SftpServerController::class, 'upload'])->name('admin.sftp-servers.files.upload');
    Route::get('/{sftpServer}/files/download', [SftpServerController::class, 'download'])->name('admin.sftp-servers.files.download');
});
