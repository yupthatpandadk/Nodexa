import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ServerContext } from '@/state/server';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import { FileObject } from '@/api/server/files/loadDirectory';
import loadDirectory from '@/api/server/files/loadDirectory';
import { encodePathSegments } from '@/helpers';
import styles from './style.module.css';

const FiveMResourcesContainer = () => {
    const server = ServerContext.useStoreState((state) => state.server.data!);
    const [files, setFiles] = useState<FileObject[]>([]);
    const [loading, setLoading] = useState(true);
    const [failure, setFailure] = useState<string | null>(null);
    const [resourceDirectory, setResourceDirectory] = useState('/resources');

    const refresh = async () => {
        setLoading(true);
        setFailure(null);
        for (const path of ['/resources', '/server-data/resources']) {
            try {
                const data = await loadDirectory(server.uuid, path);
                setResourceDirectory(path);
                setFiles(data.filter((file) => !file.isFile && !file.isSymlink));
                setLoading(false);
                return;
            } catch {
                // Continue to the next common FXServer layout.
            }
        }
        setFiles([]);
        setFailure('Ingen resource-mappe kunne læses. Kontrollér /resources eller /server-data/resources i File Manager.');
        setLoading(false);
    };

    useEffect(() => { refresh(); }, [server.uuid]);

    return (
        <ServerContentBlock title={'FiveM Resources'}>
            <div className={styles.resourcesHeader}>
                <div>
                    <span className={styles.eyebrow}>FIVEM / RESOURCES</span>
                    <h1>Resource Explorer</h1>
                    <p>Resource-mapper i {resourceDirectory}. Listen er baseret på faktiske filer, ikke en måling af scripts, der kører.</p>
                </div>
                <Link to={'/server/' + server.id + '/files'} className={styles.fileAction}>Åbn File Manager ↗</Link>
            </div>
            <section className={styles.resourceSection}>
                <div className={styles.resourceTitle}>
                    <div><strong>Resource-mapper</strong><span>{loading ? 'Indlæser…' : files.length + ' mapper fundet'}</span></div>
                    <button type={'button'} onClick={refresh} disabled={loading}>Opdatér</button>
                </div>
                {failure && <p role={'alert'} className={styles.failure}>{failure}</p>}
                {!loading && !failure && files.length === 0 && (
                    <p className={styles.empty}>Ingen mapper fundet i /resources. Åbn File Manager for at oprette eller uploade resources.</p>
                )}
                <div className={styles.resourceList}>
                    {files.map((item) => (
                        <Link key={item.key || item.name} to={'/server/' + server.id + '/files#' + encodePathSegments(resourceDirectory + '/' + item.name)}>
                            <span className={styles.resourceIcon}>▣</span>
                            <span><strong>{item.name}</strong><small>{item.name.startsWith('[') ? 'Resourcekategori' : 'Resource-mappe'}</small></span>
                            <b>Åbn filer ↗</b>
                        </Link>
                    ))}
                </div>
            </section>
            <p className={styles.resourceNote}>Start og stop individuelle resources via den konfigurerede FiveM-/txAdmin-konsol. Der gives ikke automatiske kommandoer fra denne filliste.</p>
        </ServerContentBlock>
    );
};
export default FiveMResourcesContainer;
