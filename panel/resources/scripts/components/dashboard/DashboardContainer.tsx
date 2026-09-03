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
import styled from 'styled-components/macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';

const Hero = styled.div`
    position: relative;
    display: flex;
    min-height: 190px;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    overflow: hidden;
    margin-bottom: 1.5rem;
    padding: 2rem;
    border: 1px solid rgba(73, 238, 169, 0.15);
    border-radius: 22px;
    background:
        radial-gradient(circle at 82% 20%, rgba(56, 189, 248, 0.09), transparent 19rem),
        linear-gradient(135deg, rgba(18, 45, 37, 0.96), rgba(7, 19, 16, 0.97));
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22), inset 0 1px 0 rgba(255, 255, 255, 0.025);

    &::after {
        content: '';
        position: absolute;
        width: 17rem;
        height: 17rem;
        right: -5rem;
        top: -7rem;
        border: 1px solid rgba(73, 238, 169, 0.11);
        border-radius: 50%;
        box-shadow: 0 0 90px rgba(66, 233, 166, 0.05);
    }

    @media (max-width: 700px) {
        align-items: flex-start;
        flex-direction: column;
        padding: 1.5rem;
    }
`;

const HeroBadge = styled.div`
    display: inline-flex;
    align-items: center;
    gap: 0.45rem;
    margin-bottom: 0.8rem;
    padding: 0.38rem 0.65rem;
    border: 1px solid rgba(73, 238, 169, 0.16);
    border-radius: 999px;
    color: #62efb5;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    background: rgba(66, 233, 166, 0.065);

    &::before {
        content: '';
        width: 0.42rem;
        height: 0.42rem;
        border-radius: 50%;
        background: #42e9a6;
        box-shadow: 0 0 12px rgba(66, 233, 166, 0.7);
    }
`;

const HeroTitle = styled.h1`
    margin: 0;
    color: #f3fff9;
    font-size: clamp(1.65rem, 3vw, 2.45rem);
    font-weight: 700;
    line-height: 1.08;
    letter-spacing: -0.035em;
`;

const HeroText = styled.p`
    max-width: 38rem;
    margin: 0.75rem 0 0;
    color: #91aaa1;
    font-size: 0.96rem;
    line-height: 1.6;
`;

const Metric = styled.div`
    position: relative;
    z-index: 1;
    min-width: 145px;
    padding: 1.1rem 1.2rem;
    border: 1px solid rgba(73, 238, 169, 0.13);
    border-radius: 17px;
    background: rgba(4, 14, 11, 0.52);
    backdrop-filter: blur(8px);

    strong {
        display: block;
        color: #f5fff9;
        font-size: 2rem;
        line-height: 1;
        letter-spacing: -0.04em;
    }

    span {
        display: block;
        margin-top: 0.55rem;
        color: #6f9186;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.12em;
    }
`;

const SectionHeader = styled.div`
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin: 0.25rem 0 0.85rem;

    h2 {
        margin: 0;
        color: #eafff6;
        font-size: 1rem;
        font-weight: 650;
    }

    p {
        margin: 0.25rem 0 0;
        color: #718d83;
        font-size: 0.75rem;
    }
`;

export default () => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const username = useStoreState((state) => state.user.data!.username);
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
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            <Hero>
                <div css={tw`relative z-10`}>
                    <HeroBadge>NODEXA CONTROL</HeroBadge>
                    <HeroTitle>Godt at se dig, {username}.</HeroTitle>
                    <HeroText>
                        Administrér dine game servers, ressourcer og drift fra ét samlet kontrolpanel.
                    </HeroText>
                </div>
                <Metric>
                    <strong>{servers ? servers.pagination.total : '—'}</strong>
                    <span>{showOnlyAdmin ? 'SERVEROVERSIGT' : 'DINE SERVERE'}</span>
                </Metric>
            </Hero>

            <SectionHeader>
                <div>
                    <h2>{showOnlyAdmin ? 'Alle servere' : 'Dine servere'}</h2>
                    <p>Live adgang til konsol, filer, databaser, backups og serverindstillinger.</p>
                </div>
                {rootAdmin && (
                    <div css={tw`flex items-center`}>
                        <p css={tw`uppercase text-xs text-neutral-400 mr-2`}>
                            {showOnlyAdmin ? 'Admin view' : 'Mine servere'}
                        </p>
                        <Switch
                            name={'show_all_servers'}
                            defaultChecked={showOnlyAdmin}
                            onChange={() => setShowOnlyAdmin((s) => !s)}
                        />
                    </div>
                )}
            </SectionHeader>

            {!servers ? (
                <Spinner centered size={'large'} />
            ) : (
                <Pagination data={servers} onPageSelect={setPage}>
                    {({ items }) =>
                        items.length > 0 ? (
                            items.map((server, index) => (
                                <ServerRow key={server.uuid} server={server} css={index > 0 ? tw`mt-3` : undefined} />
                            ))
                        ) : (
                            <p css={tw`text-center text-sm text-neutral-400 py-8`}>
                                {showOnlyAdmin ? 'Der er ingen andre servere at vise.' : 'Der er endnu ingen servere på din konto.'}
                            </p>
                        )
                    }
                </Pagination>
            )}
        </PageContentBlock>
    );
};
