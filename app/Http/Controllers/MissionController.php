<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use App\Models\Missions\Mission;
use App\Models\Missions\MissionComment;
use App\Helpers\ArmaConfigParser;
use App\Models\Operations\OperationMission;
use App\Models\Portal\Video;
use Storage;

class MissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index($panel = 'library')
    {
        return view('missions.index', compact('panel'));
    }

    /**
     * Gets the panel view via form input.
     *
     * @return view
     */
    public function showPanel(Request $request)
    {
        return view('missions.' . $request->panel);
    }

    /**
     * Shows the mission.
     *
     * @return view
     */
    public function showMission(Request $request)
    {
        $mission = Mission::find($request->id);
        $mission->storeConfigs();
        return view('missions.show', compact('mission'));
    }

    /**
     * Saves the mission comment.
     *
     * @return view or integer
     */
    public function saveComment(Request $request)
    {
        $form = $request->all();

        if (strlen(trim($form['text'])) == 0) {
            abort(403, 'No comment text provided');
            return;
        }

        if ($form['id'] == -1) {
            $comment = new MissionComment();
            $comment->mission_id = $form['mission_id'];
            $comment->user_id = auth()->user()->id;
            $comment->text = $form['text'];
            $comment->published = $form['published'];
            $comment->save();
        } else {
            $comment = MissionComment::find($form['id']);
            $comment->text = $form['text'];
            $comment->published = $form['published'];
            $comment->save();
        }

        if ($comment->published) {
            return view('missions.comment', compact('comment'));
        } else {
            return $comment->id;
        }
    }

    /**
     * Deletes the given comment.
     *
     * @return void
     */
    public function deleteComment(Request $request)
    {
        MissionComment::destroy($request->comment_id);
    }

    /**
     * Shows the briefing view.
     *
     * @return view
     */
    public function showBriefing(Request $request)
    {
        $mission = Mission::find($request->mission_id);
        $faction = $request->faction;
        return view('missions.briefing', compact('mission', 'faction'));
    }

    /**
     * Locks the given briefing.
     *
     * @return void
     */
    public function lockBriefing(Request $request)
    {
        $mission = Mission::find($request->mission_id);

        if (!$mission->isMine() && !auth()->user()->isAdmin()) {
            abort(403, 'You are not authorised to edit this mission');
            return;
        }

        $mission->{'locked_' . $request->faction . '_briefing'} = $request->locked;
        $mission->save();
    }

    /**
     * Shows the mission comments.
     *
     * @return view
     */
    public function showComments(Request $request)
    {
        $comments = Mission::find($request->mission_id)->comments;
        return view('missions.comments', compact('comments'));
    }

    /**
     * Uploads the given mission.
     *
     * @return integer
     */
    public function upload(Request $request)
    {
        if ($request->hasFile('file')) {
            $details = Mission::getDetailsFromName($request->file->getClientOriginalName());

            $mission = new Mission();
            $mission->user_id = auth()->user()->id;
            $mission->file_name = $request->file->getClientOriginalName();
            $mission->display_name = $request->file->getClientOriginalName();
            $mission->summary = '';
            $mission->mode = $details->mode;
            $mission->map_id = $details->map->id;
            $mission->pbo_path = '';
            $mission->save();

            $path = $request->file->storeAs(
                'missions/' . auth()->user()->id,
                $mission->id . '.pbo'
            );

            $mission->pbo_path = $path;
            $mission->save();

            $unpacked = $mission->unpack();
            $ext = ArmaConfigParser::convert($unpacked . '/description.ext');
            $mission->deleteUnpacked();

            $mission->display_name = $ext->onloadname;
            $mission->summary = $ext->onloadmission;
            $mission->save();

            return $mission->id;
        }
    }

    /**
     * Updates the given mission with the given file.
     *
     * @return integer
     */
    public function update(Request $request)
    {
        if ($request->hasFile('file')) {
            $details = Mission::getDetailsFromName($request->file->getClientOriginalName());
            $mission = Mission::find($request->mission_id);

            if (!is_null($mission)) {
                $mission->file_name = $request->file->getClientOriginalName();
                $mission->display_name = $request->file->getClientOriginalName();
                $mission->mode = $details->mode;
                $mission->map_id = $details->map->id;
                $mission->save();

                $updatedPath = $request->file->storeAs(
                    'missions/' . auth()->user()->id,
                    $mission->id . '_updated.pbo'
                );

                $publishedPath = 'missions/' . auth()->user()->id . '/' . $mission->id . '.pbo';

                Storage::delete($mission->pbo_path);
                Storage::move($updatedPath, $publishedPath);

                $mission->pbo_path = $publishedPath;
                $mission->save();

                $unpacked = $mission->unpack();
                $ext = ArmaConfigParser::convert($unpacked . '/description.ext');
                $mission->deleteUnpacked();

                $mission->display_name = $ext->onloadname;
                $mission->summary = $ext->onloadmission;
                $mission->save();

                return $mission->id;
            }
        }
    }

    /**
     * Uploads the given media to the mission.
     *
     * @return view
     */
    public function uploadMedia(Request $request)
    {
        $mission = Mission::find($request->mission_id);

        $mission
            ->addMedia($request->file('file'))
            ->withCustomProperties(['user_id' => auth()->user()->id])
            ->toCollection('images');

        $media = $mission->getMedia('images')->last();

        return view('missions.media-item', compact('media', 'mission'));
    }

    /**
     * Deletes the given media item.
     *
     * @return void
     */
    public function deleteMedia(Request $request)
    {
        $mission = Mission::find($request->mission_id);
        $media = $mission->media->find($request->media_id);

        if (($media->getCustomProperty('user_id', -1) == auth()->user()->id || auth()->user()->isAdmin())) {
            $mission->deleteMedia($media);
        }
    }

    /**
     * Adds the given video to the given mission.
     *
     * @return view
     */
    public function addVideo(Request $request)
    {
        $video_key = Video::parseUrl($request->video_url);
        
        $video = new Video();

        $video->user_id = auth()->user()->id;
        $video->mission_id = $request->mission_id;
        $video->video_key = $video_key;

        $video->save();

        return view('missions.video-item', compact('video'));
    }

    /**
     * Removes the given video from the given mission.
     *
     * @return void
     */
    public function removeVideo(Request $request)
    {
        Video::destroy($request->video_id);
    }
}
