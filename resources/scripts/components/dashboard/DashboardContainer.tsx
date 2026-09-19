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
    background: linear-gradient(135deg, rgba(99,102,241,.16), rgba(139,92,246,.08) 55%, rgba(14,165,233,.10));
    border: 1px solid rgba(148,163,184,.13);
    border-radius: 18px;
    padding: 26px;
    margin-bottom: 22px;
    box-shadow: 0 18px 50px rgba(0,0,0,.18);
`;
const EmptyState = styled.div`
    min-height: 310px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 32px 20px;
    border-radius: 18px;
    border: 1px dashed rgba(148,163,184,.20);
    background: rgba(15,23,42,.45);
`;
const EmptyIcon = styled.div`
    width: 62px; height: 62px; margin: 0 auto 18px; border-radius: 18px;
    display:flex; align-items:center; justify-content:center; font-size:26px; font-weight:800;
    color:#fff; background:linear-gradient(135deg,#6366f1,#8b5cf6);
    box-shadow:0 12px 34px rgba(99,102,241,.28);
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
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            <Hero>
                <div css={tw`flex items-center justify-between flex-wrap`}>
                    <div>
                        <div css={tw`uppercase text-xs font-semibold tracking-wider text-indigo-300 mb-2`}>Nodexa Control Panel</div>
                        <h1 css={tw`text-2xl sm:text-3xl font-bold text-white mb-2`}>Dine servere</h1>
                        <p css={tw`text-sm text-neutral-400 m-0`}>Administrér, overvåg og åbn dine servere fra ét samlet dashboard.</p>
                    </div>
                    <div css={tw`mt-4 sm:mt-0 text-xs text-neutral-400`}>
                        <span css={tw`inline-flex items-center px-3 py-2 rounded-lg bg-neutral-900 bg-opacity-50`}>Nodexa • Online</span>
                    </div>
                </div>
            </Hero>
            {rootAdmin && (
                <div css={tw`mb-2 flex justify-end items-center`}>
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
        </PageContentBlock>
    );
};
