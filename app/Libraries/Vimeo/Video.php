<?php

namespace App\Libraries\Vimeo;

class Video extends Api
{
    /**
     * Upload a video with the given upload size.
     *
     * @param  int  $size
     * @return \Illuminate\Http\Client\Response
     */
    public function upload(int $size)
    {
        return $this->client(true)
            ->accept('application/vnd.vimeo.*+json;version=3.4')
            ->post('me/videos', [
                'upload' => [
                    'approach' => 'tus',
                    'size' => $size,
                ],
            ]);
    }

    /**
     * Find the video with the given ID.
     *
     * @param  int|string  $id
     * @return \Illuminate\Http\Client\Response
     */
    public function find(int|string $id)
    {
        $id = $this->parseId($id);

        return $this->client(true)->get("videos/{$id}");
    }

    /**
     * Find the video with the given ID.
     *
     * @param  int|string  $id
     * @param  array  $data
     * @return \Illuminate\Http\Client\Response
     */
    public function update(int|string $id, array $data)
    {
        $id = $this->parseId($id);

        return $this->client(true)->patch("videos/{$id}", $data);
    }

    /**
     * Parse the given Vimeo video ID.
     *
     * @param  int|string  $id
     * @return string
     */
    protected function parseId(int|string $id)
    {
        if (preg_match('/^videos\/([0-9]+)/', (string) $id, $matches)) {
            return $matches[1];
        }

        return $id;
    }
}
