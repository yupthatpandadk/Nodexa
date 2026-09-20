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
    background: radial-gradient(circle at top right, rgba(124,58,237,.22), transparent 34%), linear-gradient(135deg,#101827,#0b1220);
    border: 1px solid rgba(139,92,246,.22);
    border-radius: 22px;
    padding: 28px;
    margin-bottom: 22px;
    box-shadow: 0 24px 60px rgba(0,0,0,.28);
`;
const ServerPanel = styled.div`
    background: rgba(10,18,32,.72);
    border: 1px solid rgba(148,163,184,.11);
    border-radius: 20px;
    padding: 18px;
    box-shadow: 0 18px 50px rgba(0,0,0,.16);
`;
const EmptyState = styled.div`
    min-height: 330px;
    display:flex; align-items:center; justify-content:center; text-align:center;
    padding:36px 20px; border-radius:18px;
    border:1px dashed rgba(139,92,246,.28);
    background:linear-gradient(145deg,rgba(15,23,42,.72),rgba(17,24,39,.44));
`;
const EmptyIcon = styled.div`
    width:68px;height:68px;margin:0 auto 18px;border-radius:20px;
    display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;
    color:#fff;background:linear-gradient(135deg,#7c3aed,#8b5cf6);
    box-shadow:0 14px 38px rgba(124,58,237,.32);
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
                        <div css={tw`uppercase text-xs font-semibold tracking-wider text-purple-300 mb-2`}>Nodexa Cloud</div>
                        <h1 css={tw`text-2xl sm:text-3xl font-bold text-white mb-2`}>Serveroversigt</h1>
                        <p css={tw`text-sm text-neutral-400 m-0`}>Alt du behøver for at administrere dine servere samlet ét sted.</p>
                    </div>
                    <div css={tw`mt-4 sm:mt-0 flex items-center`}>
                        <span css={tw`inline-flex items-center px-4 py-2 rounded-lg bg-neutral-900 bg-opacity-50 text-xs text-green-300`}>● System online</span>
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
            <ServerPanel>
            <div css={tw`flex items-center justify-between mb-4`}>
                <div>
                    <h2 css={tw`text-lg font-semibold text-white m-0`}>Mine servere</h2>
                    <p css={tw`text-xs text-neutral-500 mt-1 m-0`}>Status og ressourceforbrug opdateres automatisk.</p>
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
