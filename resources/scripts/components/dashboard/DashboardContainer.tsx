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
    position: relative; overflow: hidden;
    background: linear-gradient(135deg, rgba(124,58,237,.18), rgba(8,14,27,.97) 52%, rgba(14,165,233,.09));
    border: 1px solid rgba(139,92,246,.20); border-radius: 20px; padding: 26px 28px; margin-bottom: 18px;
    box-shadow: 0 16px 44px rgba(0,0,0,.18);
    &:after { content: ''; position:absolute; width:220px; height:220px; right:-70px; top:-120px; border-radius:999px; background:rgba(56,189,248,.08); pointer-events:none; }
    @media (max-width: 640px) { padding: 21px 19px; border-radius: 17px; }
`;
const StatCard = styled.div`
    min-width: 112px; padding: 11px 14px; border-radius: 12px; background: rgba(255,255,255,.045);
    border: 1px solid rgba(255,255,255,.07); backdrop-filter: blur(8px);
`;
const ServerPanel = styled.div`
    background: rgba(8,14,27,.76); border: 1px solid rgba(148,163,184,.11); border-radius: 18px;
    padding: 18px; box-shadow: 0 12px 34px rgba(0,0,0,.14);
    @media (max-width: 640px) { padding: 14px; border-radius: 16px; }
`;
const EmptyState = styled.div`
    min-height: 230px; display:flex; align-items:center; justify-content:center; text-align:center;
    padding: 28px 20px; border-radius: 15px; border: 1px dashed rgba(139,92,246,.22);
    background: radial-gradient(circle at 50% 0%,rgba(124,58,237,.09),transparent 46%), rgba(15,23,42,.26);
`;
const EmptyIcon = styled.div`
    width: 56px; height: 56px; margin: 0 auto 15px; border-radius: 16px; display:flex; align-items:center;
    justify-content:center; font-size:22px; font-weight:800; color:#fff;
    background:linear-gradient(135deg,#7c3aed,#6366f1); box-shadow:0 10px 28px rgba(124,58,237,.24);
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
                <div css={tw`relative z-10 flex items-center justify-between flex-wrap`}>
                    <div>
                        <div css={tw`uppercase text-xs font-semibold tracking-wider text-purple-300 mb-2`}>Nodexa Control Panel</div>
                        <h1 css={tw`text-2xl sm:text-3xl font-bold tracking-tight text-white mb-2`}>Velkommen tilbage</h1>
                        <p css={tw`text-sm text-neutral-400 m-0`}>Administrér servere, status og ressourcer fra ét samlet dashboard.</p>
                    </div>
                    <div css={tw`mt-5 sm:mt-0 flex gap-2`}>
                        <StatCard><div css={tw`text-xs text-neutral-500 mb-1`}>Servere</div><div css={tw`text-lg text-white font-semibold`}>{servers ? servers.pagination.total : '—'}</div></StatCard>
                        <StatCard><div css={tw`text-xs text-neutral-500 mb-1`}>Platform</div><div css={tw`text-sm text-green-300 font-semibold`}>● Online</div></StatCard>
                    </div>
                </div>
            </Hero>
            {rootAdmin && (
                <div css={tw`mb-4 flex justify-end items-center px-1`}>
                    <p css={tw`text-xs text-neutral-400 mr-2`}>
                        {showOnlyAdmin ? "Viser alle servere" : 'Kun dine servere'}
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
                    <h2 css={tw`text-base sm:text-lg font-semibold text-white m-0`}>Dine servere</h2>
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
