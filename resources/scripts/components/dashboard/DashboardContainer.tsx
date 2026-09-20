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

const DashboardShell = styled.div`
    display: grid; gap: 16px;
    --nodexa-accent: var(--nodexa-user-accent, #4f8cff);
`;
const Hero = styled.section`
    position: relative; overflow: hidden; min-height: 138px; display:flex; align-items:center;
    background: linear-gradient(110deg, #0d2a43 0%, #0b2238 58%, #0b1b2d 100%);
    border: 1px solid color-mix(in srgb, var(--nodexa-accent) 30%, #22304a);
    border-radius: 16px; padding: 24px;
    box-shadow: 0 14px 38px rgba(0,0,0,.22);
    &:after { content:''; position:absolute; right:-60px; top:-100px; width:260px; height:260px; border-radius:999px; border:1px solid rgba(255,255,255,.035); box-shadow:0 0 0 44px rgba(255,255,255,.012); }
    @media(max-width:640px){ min-height:0; padding:20px; }
`;
const Eyebrow = styled.div`
    display:inline-flex; align-items:center; gap:7px; color:#75a7ff; font-size:11px; font-weight:700; letter-spacing:.08em; text-transform:uppercase;
    &:before { content:''; width:7px; height:7px; border-radius:999px; background:var(--nodexa-accent); box-shadow:0 0 0 4px rgba(139,92,246,.10); }
`;
const Stats = styled.div`
    display:grid; grid-template-columns:repeat(2,minmax(108px,1fr)); gap:10px;
    @media(max-width:640px){ width:100%; margin-top:18px; }
`;
const StatCard = styled.div`
    padding:12px 14px; border-radius:10px; background:#151f31; border:1px solid #22304a;
`;
const Section = styled.section`
    background:transparent; border:0; border-radius:0; padding:0;
    box-shadow:none; @media(max-width:640px){ padding:0; }
`;
const SectionHeader = styled.div`
    display:flex; align-items:center; justify-content:space-between; gap:16px; padding:1px 0 10px; border-bottom:0; margin-bottom:4px;
`;
const CountBadge = styled.span`
    white-space:nowrap; font-size:11px; font-weight:600; color:#a3a3a3; padding:6px 9px; border-radius:8px; background:rgba(255,255,255,.035); border:1px solid rgba(255,255,255,.055);
`;
const EmptyState = styled.div`
    min-height:190px; display:flex; align-items:center; justify-content:center; text-align:center; padding:28px 20px; border-radius:14px;
    background:#0d1523; border:1px solid #22304a;
`;
const EmptyIcon = styled.div`
    width:48px;height:48px;margin:0 auto 14px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:19px;font-weight:800;color:#fff;
    background:linear-gradient(135deg,var(--nodexa-accent),color-mix(in srgb,var(--nodexa-accent) 68%,white)); box-shadow:0 9px 24px rgba(99,102,241,.20);
`;
const AdminFilter = styled.div`
    display:flex; justify-content:flex-end; align-items:center; min-height:28px;
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
            <DashboardShell>
                <Hero>
                    <div css={tw`relative z-10 w-full flex items-center justify-between flex-wrap`}>
                        <div css={tw`max-w-xl`}>
                            <Eyebrow>Nodexa Control</Eyebrow>
                            <h1 css={tw`text-2xl sm:text-3xl font-bold tracking-tight text-white mt-3 mb-2`}>Godt at se dig.</h1>
                            <p css={tw`text-sm text-neutral-400 leading-relaxed m-0`}>Administrér dine game servers, ressourcer og drift fra ét samlet kontrolpanel.</p>
                        </div>
                        <Stats>
                            <StatCard>
                                <div css={tw`text-xs text-neutral-500 mb-1`}>Serveroversigt</div>
                                <div css={tw`text-xl text-white font-semibold`}>{servers ? servers.pagination.total : '—'}</div>
                            </StatCard>
                            <StatCard>
                                <div css={tw`text-xs text-neutral-500 mb-1`}>Platform</div>
                                <div css={tw`text-sm text-green-300 font-semibold`}>● Online</div>
                            </StatCard>
                        </Stats>
                    </div>
                </Hero>

                {rootAdmin && (
                    <AdminFilter>
                        <p css={tw`text-xs text-neutral-500 mr-2 m-0`}>{showOnlyAdmin ? 'Viser alle servere' : 'Kun dine servere'}</p>
                        <Switch name={'show_all_servers'} defaultChecked={showOnlyAdmin} onChange={() => setShowOnlyAdmin((s) => !s)} />
                    </AdminFilter>
                )}

                <Section>
                    <SectionHeader>
                        <div>
                            <h2 css={tw`text-base font-semibold text-white m-0`}>Alle servere</h2>
                            <p css={tw`text-xs text-neutral-500 mt-1 m-0`}>Live adgang til konsol, filer, databaser, backups og serverindstillinger.</p>
                        </div>
                        {servers && <CountBadge>{servers.pagination.total} {servers.pagination.total === 1 ? 'server' : 'servere'}</CountBadge>}
                    </SectionHeader>

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
                                            <h2 css={tw`text-lg font-semibold text-white mb-2`}>{showOnlyAdmin ? 'Ingen andre servere' : 'Ingen servere endnu'}</h2>
                                            <p css={tw`text-sm text-neutral-500 max-w-md m-0 leading-relaxed`}>
                                                {showOnlyAdmin ? 'Der er ingen andre servere at vise.' : 'Når din første server bliver oprettet, vises den her med status og ressourceforbrug.'}
                                            </p>
                                        </div>
                                    </EmptyState>
                                )
                            }
                        </Pagination>
                    )}
                </Section>
            </DashboardShell>
        </PageContentBlock>
    );
};
