{**
 * updateFailed.tpl
 *
 * Copyright (c) 2006 The Public Knowledge Project
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * Display a "metadata index update failed" message with details
 *
 * $Id$
 *}

{assign var="pageTitle" value="admin.archives.manage.updateIndex"}

{include file="common/header.tpl"}

<p>{translate key="admin.archive.manage.updateIndex.failure"}</p>
<ul>
{foreach from=$errors item=error}
	<li><span class="formError">{$error}</span></li>
{foreachelse}
	<li><span class="formError">{translate key="admin.archive.manage.updateIndex.failure.generic"}</span></li>
{/foreach}
</ul>

<a href="{url op="manage" path=$archiveId}">{translate key="admin.archive.manage.updateIndex.return"}</a>

{include file="common/footer.tpl"}