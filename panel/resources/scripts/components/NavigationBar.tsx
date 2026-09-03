import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';

const NavigationShell = styled.div`
    position: sticky;
    top: 0;
    z-index: 40;
    width: 100%;
    overflow-x: auto;
    border-bottom: 1px solid rgba(73, 238, 169, 0.11);
    background: rgba(5, 13, 11, 0.84);
    backdrop-filter: blur(18px);
    box-shadow: 0 18px 45px rgba(0, 0, 0, 0.18);
`;

const Brand = styled(Link)`
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.35rem 0.75rem;
    border-radius: 14px;
    color: #f1fff9;
    text-decoration: none;

    &:hover {
        color: #ffffff;
        background: rgba(66, 233, 166, 0.045);
    }
`;

const BrandMark = styled.span`
    display: inline-flex;
    width: 2.45rem;
    height: 2.45rem;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(73, 238, 169, 0.32);
    border-radius: 13px;
    color: #06100e;
    font-size: 1.1rem;
    font-weight: 800;
    background: linear-gradient(145deg, #65f2b8, #2ddc98);
    box-shadow: 0 8px 30px rgba(45, 220, 152, 0.2);
`;

const BrandName = styled.span`
    display: block;
    color: #effbf6;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.05;
    letter-spacing: -0.02em;
`;

const BrandSub = styled.span`
    display: block;
    margin-top: 0.2rem;
    color: #6f9186;
    font-size: 0.58rem;
    font-weight: 700;
    letter-spacing: 0.16em;
`;

const RightNavigation = styled.div`
    gap: 0.35rem;

    & > a,
    & > button,
    & > .navigation-link {
        display: flex;
        min-width: 2.7rem;
        height: 2.7rem;
        align-items: center;
        justify-content: center;
        padding: 0 0.8rem;
        border: 1px solid transparent;
        border-radius: 12px;
        color: #8ca49b;
        text-decoration: none;
        cursor: pointer;
        transition: all 150ms ease;
    }

    & > a:hover,
    & > button:hover,
    & > .navigation-link:hover,
    & > a.active {
        color: #eafff6;
        border-color: rgba(73, 238, 169, 0.18);
        background: rgba(66, 233, 166, 0.08);
        box-shadow: inset 0 0 0 1px rgba(66, 233, 166, 0.025), 0 8px 26px rgba(0, 0, 0, 0.12);
    }

    & > a.active {
        color: #55eeb0;
    }
`;

export default () => {
    const rootAdmin = useStoreState((state: ApplicationStore) => state.user.data!.rootAdmin);
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <NavigationShell>
            <SpinnerOverlay visible={isLoggingOut} />
            <div className={'mx-auto w-full flex items-center h-[4.5rem] max-w-[1320px] px-2 sm:px-4'}>
                <div id={'logo'} className={'flex-1 min-w-[190px]'}>
                    <Brand to={'/'}>
                        <BrandMark>N</BrandMark>
                        <span>
                            <BrandName>Nodexa</BrandName>
                            <BrandSub>GAME SERVER CLOUD</BrandSub>
                        </span>
                    </Brand>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {rootAdmin && (
                        <Tooltip placement={'bottom'} content={'Nodexa Admin'}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
                    <Tooltip placement={'bottom'} content={'Account Settings'}>
                        <NavLink to={'/account'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}>
                        <button onClick={onTriggerLogout}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </div>
        </NavigationShell>
    );
};
