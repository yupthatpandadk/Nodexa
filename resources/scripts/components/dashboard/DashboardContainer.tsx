import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import Switch from '@/components/elements/Switch';
import tw from 'twin.macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import styled from 'styled-components/macro';

const Hero = styled.div`
    background: linear-gradient(135deg,rgba(124,58,237,.18),rgba(10,16,30,.96) 48%,rgba(14,165,233,.08));
    border:1px solid rgba(139,92,246,.22); border-radius:24px; padding:30px; margin-bottom:22px;
    box-shadow:0 24px 70px rgba(0,0,0,.28);
`;
const StatCard = styled.div`
    min-width:112px; padding:13px 16px; border-radius:15px; background:rgba(255,255,255,.045);
    border:1px solid rgba(255,255,255,.07);
`;
const ServerPanel = styled.div`
    background:rgba(8,14,27,.88); border:1px solid rgba(148,163,184,.12); border-radius:22px;
    padding:20px; box-shadow:0 18px 50px rgba(0,0,0,.18);
`;
const EmptyState = styled.div`
    min-height:360px; display:flex; align-items:center; justify-content:center; text-align:center;
    padding:38px 20px; border-radius:18px; border:1px dashed rgba(139,92,246,.30);
    background:radial-gradient(circle at 50% 0%,rgba(124,58,237,.12),transparent 42%),rgba(15,23,42,.42);
`;
const EmptyIcon = styled.div`
    width:72px;height:72px;margin:0 auto 18px;border-radius:22px;display:flex;align-items:center;
    justify-content:center;font-size:30px;font-weight:800;color:#fff;
    background:linear-gradient(135deg,#7c3aed,#6366f1);box-shadow:0 16px 42px rgba(124,58,237,.30);
`;

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);
    const [showOnlyAdmin, setShowOnlyAdmin] = usePersistedState(`${uuid}:show_all_servers`, false);

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        ['/api/client/servers', showOnlyAdmin && rootAdmin, page],
        () => getServers({ page, type: showOnlyAdmin && rootAdmin ? 'admin' : undefined })
    );

    useEffect(() => {
        setPage(1);
    }, [showOnlyAdmin]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        // Don't use react-router to handle changing this part of the URL, otherwise it
        // triggers a needless re-render. We just want to track this in the URL incase the
        // user refreshes the page.
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    return (
        <PageContentBlock title={'Mine servere'} showFlashKey={'dashboard'}>
            <Hero>
                <div css={tw`flex items-center justify-between flex-wrap`}>
                    <div>
                        <div css={tw`uppercase text-xs font-semibold tracking-wider text-purple-300 mb-2`}>Nodexa Control Panel</div>
                        <h1 css={tw`text-2xl sm:text-3xl font-bold text-white mb-2`}>Velkommen tilbage</h1>
                        <p css={tw`text-sm text-neutral-400 m-0`}>Administrér servere, status og ressourcer fra ét samlet dashboard.</p>
                    </div>
                    <div css={tw`mt-5 sm:mt-0 flex flex-wrap gap-2`}>
                        <StatCard><div css={tw`text-xs text-neutral-500 mb-1`}>Servere</div><div css={tw`text-lg text-white font-semibold`}>{servers ? servers.pagination.total : '—'}</div></StatCard>
                        <StatCard><div css={tw`text-xs text-neutral-500 mb-1`}>Platform</div><div css={tw`text-sm text-green-300 font-semibold`}>● Online</div></StatCard>
                    </div>
                </div>
            </Hero>
            {rootAdmin && (
                <div css={tw`mb-4 flex justify-end items-center px-1`}>
                    <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                        {showOnlyAdmin ? "Showing others' servers" : 'Showing your servers'}
                    </p>
                    <Switch
                        name={'show_all_servers'}
                        defaultChecked={showOnlyAdmin}
                        onChange={() => setShowOnlyAdmin((s) => !s)}
                    />
                </div>
            )}
            <ServerPanel>
            <div css={tw`flex items-center justify-between mb-4`}>
                <div>
                    <h2 css={tw`text-lg font-semibold text-white m-0`}>Dine servere</h2>
                    <p css={tw`text-xs text-neutral-500 mt-1 m-0`}>Åbn en server for console, filer, backups og indstillinger.</p>
                </div>
                {servers && <span css={tw`text-xs text-neutral-400 px-3 py-2 rounded-lg bg-neutral-900 bg-opacity-50`}>{servers.pagination.total} server(e)</span>}
            </div>
            {!servers ? (
                <Spinner centered size={'large'} />
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                            items.map((server, index) => (
                                <ServerRow key={server.uuid} server={server} css={index > 0 ? tw`mt-2` : undefined} />
                            ))
                        ) : (
                            <EmptyState>
                                <div>
                                    <EmptyIcon>N</EmptyIcon>
                                    <h2 css={tw`text-xl font-semibold text-white mb-2`}>
                                        {showOnlyAdmin ? 'Ingen andre servere' : 'Ingen servere endnu'}
                                    </h2>
                                    <p css={tw`text-sm text-neutral-400 max-w-md m-0`}>
                                        {showOnlyAdmin
                                            ? 'Der er ingen andre servere at vise lige nu.'
                                            : 'Der er endnu ingen servere tilknyttet din konto. Når en server bliver oprettet, vises den her.'}
                                    </p>
                                </div>
                            </EmptyState>
                        )
                    }
                </Pagination>
            )}
            </ServerPanel>
        </PageContentBlock>
    );
};
